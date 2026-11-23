"""Orden real del render PHP, sin WordPress ni llamadas a la API."""
import json
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]


class ProgramOrderTest(unittest.TestCase):
    def render(self, name, videos):
        html = self.render_category(name, videos)
        return re.findall(r'<h[23][^>]*>(?:<span>)?(.*?)(?:</span>)?</h[23]>', html)

    def render_category(self, name, videos, banner=None, slug=None):
        bootstrap = r'''
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
function remove_accents($s) { return iconv('UTF-8', 'ASCII//TRANSLIT', $s); }
function home_url($s = '') { return 'https://tuia.tv' . $s; }
function esc_html($s) { return htmlspecialchars($s); }
function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars($s); }
function current_time($type) { return time(); }
'''
        code = bootstrap
        for filename in ['adc-utils.php', 'adc-api.php']:
            code += '\nrequire ' + json.dumps(str(ROOT / filename)) + ';'
        source = (ROOT / 'adc-video-display.php').read_text()
        code += '\n' + source[source.index('class ADC_Video_Display'):source.index('// Initialize plugin')]
        code += r'''
class FixtureAPI extends ADC_API {
    public $videos;
    public $name;
    public $banner;
    public function __construct() {}
    public function get_programs() {
        $category = ['id' => 348, 'name' => $this->name];
        if ($this->banner !== null) { $category['banner'] = $this->banner; }
        return [$category];
    }
    public function get_materials($id) { return $this->videos; }
    public function get_subcategories($id) { return []; }
    public function get_season_order() { return [6 => 1, 7 => 2]; }
    public function get_season_name($id) { return 'Season ' . $id; }
}
$api = new FixtureAPI();
'''
        code += '$api->videos = json_decode(' + json.dumps(json.dumps(videos)) + ', true);\n'
        code += '$api->name = ' + json.dumps(name) + ';\n'
        code += '$api->banner = ' + json.dumps(banner) + ';\n'
        code += '$slug = ' + json.dumps(slug) + ';\n'
        code += r'''
$class = new ReflectionClass('ADC_Video_Display');
$display = $class->newInstanceWithoutConstructor();
foreach (['api' => $api, 'options' => [], 'language' => 'es'] as $key => $value) {
    $property = $class->getProperty($key);
    $property->setAccessible(true);
    $property->setValue($display, $value);
}
$method = $class->getMethod('display_category_videos');
$method->setAccessible(true);
echo $method->invoke($display, $slug ?? ADC_Utils::slugify($api->name));
'''
        result = subprocess.run(['php', '-r', code], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stderr, '')
        return result.stdout

    def video(self, number, season=6, date='2026-10-28 19:40:02'):
        return dict(title=f'{number:02d} - Clase', season=season, releaseDate=date,
                    thumbnail='', duration='05:00')

    def test_numbered_order_survives_new_publications_and_seasons(self):
        videos = [self.video(6, date='2026-11-11 16:10:03'), self.video(1),
                  self.video(3), self.video(2), self.video(5), self.video(4)]
        expected = ['Season 6'] + [f'{n:02d} - Clase' for n in range(1, 7)]
        self.assertEqual(self.render('Siguiendo la parasha', videos), expected)
        videos = [self.video(2, season=7), self.video(8, date='2026-11-12 16:00:00'),
                  self.video(1, season=7), self.video(7)] + videos
        expected = ['Season 6'] + [f'{n:02d} - Clase' for n in range(1, 9)]
        expected += ['Season 7', '01 - Clase', '02 - Clase']
        self.assertEqual(self.render('Siguiendo la parasha', videos), expected)

    def test_other_program_keeps_newest_first(self):
        videos = [self.video(1), self.video(6, date='2026-11-11 16:10:03')]
        self.assertEqual(self.render('Otro programa', videos),
                         ['Season 6', '06 - Clase', '01 - Clase'])

    def test_category_renders_its_banner_alongside_videos(self):
        url = 'https://adc.tutorah.tv/img/categorias/banners/348_ia.png'
        html = self.render_category('Programa de prueba', [self.video(1)], url)
        self.assertIn(f'src="{url}" alt="Programa de prueba"', html)
        self.assertIn('01 - Clase', html)

    def test_missing_or_empty_banner_does_not_add_an_image(self):
        for banner in (None, ''):
            with self.subTest(banner=banner):
                html = self.render_category('Otro programa', [self.video(1)], banner)
                self.assertNotIn('adc-category-banner', html)
                self.assertIn('01 - Clase', html)

    def test_banner_does_not_bypass_category_eligibility(self):
        url = 'https://adc.tutorah.tv/img/categorias/banners/348_ia.png'
        for html in (self.render_category('Sin videos', [], url),
                     self.render_category('Otro programa', [self.video(1)], url, slug='no-existe')):
            self.assertIn('adc-error', html)
            self.assertNotIn(url, html)


if __name__ == '__main__':
    unittest.main()
