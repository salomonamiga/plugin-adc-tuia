#!/bin/bash

cd /home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC

git add .
git commit -m "Auto-commit desde servidor $(date +'%d-%m-%Y %H:%M:%S')" >/dev/null 2>&1
git push origin main >/dev/null 2>&1
