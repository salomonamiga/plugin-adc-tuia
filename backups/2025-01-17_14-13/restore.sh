#!/bin/bash

# Script de restauración automática
# Ejecutar si hay problemas: bash restore.sh

echo "🔄 Restaurando archivos originales..."

# Restaurar script.js
curl -T script.js.original ftp://gcam1029.siteground.biz/tuia.tv/public_html/wp-content/plugins/ADC/script.js --user "admin@tuia.tv:Sa0506Tt59@"
echo "✅ script.js restaurado"

# Restaurar adc-video-display.php
curl -T adc-video-display.php.original ftp://gcam1029.siteground.biz/tuia.tv/public_html/wp-content/plugins/ADC/adc-video-display.php --user "admin@tuia.tv:Sa0506Tt59@"
echo "✅ adc-video-display.php restaurado"

# Restaurar adc-search.php
curl -T adc-search.php.original ftp://gcam1029.siteground.biz/tuia.tv/public_html/wp-content/plugins/ADC/adc-search.php --user "admin@tuia.tv:Sa0506Tt59@"
echo "✅ adc-search.php restaurado"

# Restaurar adc-api.php
curl -T adc-api.php.original ftp://gcam1029.siteground.biz/tuia.tv/public_html/wp-content/plugins/ADC/adc-api.php --user "admin@tuia.tv:Sa0506Tt59@"
echo "✅ adc-api.php restaurado"

echo "🎉 Restauración completa!"