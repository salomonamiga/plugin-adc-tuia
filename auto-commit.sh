#!/bin/bash

cd /home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC

# Agregar todos los cambios (modificados, nuevos y eliminados)
git add -A

# Hacer commit solo si hay cambios pendientes
if ! git diff --cached --quiet; then
  git commit -m "Auto commit $(date '+%Y-%m-%d %H:%M:%S')"
  git push github main
fi

