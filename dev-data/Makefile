# ------------------------------------------
# 国内IP信息数据收集转换
#
# 1. 纯真库 (UTF-8 encoding): raw/cz88.txt.gz
#    下载源：http://update.cz88.net/soft/setup.zip
#
# 2. 全球国家IP数据库: raw/GeoIPCountryWhois.csv
#    下载源：http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip
#
# 3. 国内城市、县区分级数据: raw/area_list.txt
#    生成时会自动忽略海外信息
#
# 4. 纯真IP地址修正转换：raw/area_fixed.txt
#    主要是针对部分大学地区信息进行标注
#
# 地区数据生成:
#   1. 生成全球国家表并修正 HK/MO/TW 并入中国
#   2. 从纯真IP数据中提取中国相关的数据补充
# ------------------------------------------

PHP		= php

all:
	@clear
	@echo "Usage: make <target> [env vars]"
	@echo "---------------------------------------------------"
	@echo "Target list:"
	@echo " 1. data,ipconv.dat     Database in UTF-8 encoding"
	@echo " 2. clean                Clean all data"
	@echo 
	@echo "Env variables:"
	@echo " PHP                     path of php-cli, default is: php"
	@echo "---------------------------------------------------"
	@echo "Usage example:"
	@echo "make ipconv.dat PHP=/pat/to/php"
	@echo

data: ipconv.dat
	@echo "DONE. plz copy and use the file: ./$?"

ipconv.dat: runtime/ip_data.txt
	php ../build-data.php $? $@

runtime/ip_data.txt: runtime/area_dict.txt runtime/area.inc.php runtime/ip2country.dat
	gzip -d raw/cz88.txt.gz
	php gen_ip_data.php > $@
	gzip raw/cz88.txt

runtime/ip2country.dat: runtime/ip_country.txt
	php ../build-data.php $? $@

runtime/ip_country.txt:
	gzip -d raw/GeoIPCountryWhois.csv.gz
	awk -F, '{ print $$1 "\t" $$2 "\t" $$5 }' raw/GeoIPCountryWhois.csv | sed -e 's#"##g' > $@
	gzip raw/GeoIPCountryWhois.csv

runtime/country.inc.php:
	php gen_country.php raw/country.3166 > $@

runtime/area.inc.php: runtime/country.inc.php
	php gen_area.php php > $@

runtime/area_dict.txt:
	php gen_area.php dict > $@

clean:
	rm -f ipconv.dat runtime/*

