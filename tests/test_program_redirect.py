"""TT-053: comprueba el hook aislado sin cargar WordPress ni consultar datos."""
import json
from pathlib import Path
import subprocess
import unittest

SOURCE = Path(__file__).resolve().parents[1] / 'adc-video-display.php'


class ProgramRedirectTest(unittest.TestCase):
    def run_hook(self, uri):
        source = SOURCE.read_text()
        start_marker = '// TT-053: alias puntual del programa, antes de resolver las URLs.'
        if start_marker not in source:
            return 'NO_REDIRECT'
        hook = source.split(start_marker, 1)[1].split('// Evita el canonical redirect', 1)[0]
        bootstrap = '''
function add_action($name, $callback, $priority) {
    if ($name !== 'init' || $priority !== 1) { throw new Exception('hook inesperado'); }
    $callback();
}
function wp_unslash($value) { return stripslashes($value); }
function wp_parse_url($value, $component) { return parse_url($value, $component); }
function home_url($path) { return 'https://tuia.tv' . $path; }
function wp_safe_redirect($url, $status) { echo json_encode([$url, $status]); }
'''
        code = bootstrap + '$_SERVER["REQUEST_URI"] = ' + json.dumps(uri) + ';\n' + hook
        code += '\necho "NO_REDIRECT";'
        return subprocess.check_output(['php', '-r', code], text=True)

    def test_alias(self):
        for uri in ['/programa/siguendo-la-parasha/', '/programa/siguendo-la-parasha',
                    '/programa/siguendo-la-parasha/?utm_source=prueba']:
            with self.subTest(uri=uri):
                self.assertEqual(json.loads(self.run_hook(uri)),
                                 ['https://tuia.tv/programa/siguiendo-la-parasha/', 301])

    def test_other_routes_unchanged(self):
        for uri in ['/programa/siguiendo-la-parasha/', '/',
                    '/programa/siguendo-la-parasha/video/',
                    '/programa/siguendo-la-parasha-otra/',
                    '/pt/programa/siguendo-la-parasha/',
                    '/en/program/siguendo-la-parasha/',
                    '/wp-admin/', '/?next=/programa/siguendo-la-parasha/']:
            with self.subTest(uri=uri):
                self.assertEqual(self.run_hook(uri), 'NO_REDIRECT')


if __name__ == '__main__':
    unittest.main()
