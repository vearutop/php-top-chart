screen -dmS hhvm-top ~/php-top-chart.php -i 1 -t 540 -n hhvm -c load_average la1 -c mem memUsed,memFree -c worker_memory hhvm:mem -c worker_cpu hhvm:cpu -c misc mysqld:cpu,beam.smp:cpu,redis-server:cpu,nginx:cpu -c misc_mem mysqld:mem,beam.smp:mem,redis-server:mem,nginx:mem
sleep 120
siege -c150 -b -t300s --log=./siege.log -q -f ~/url-list.hhvm.txt
sleep 120


screen -dmS php54-top ~/php-top-chart.php -i 1 -t 540 -n php54 -c load_average la1 -c mem memUsed,memFree -c worker_memory php-fpm:mem -c worker_cpu php-fpm:cpu -c misc mysqld:cpu,beam.smp:cpu,redis-server:cpu,nginx:cpu -c misc_mem mysqld:mem,beam.smp:mem,redis-server:mem,nginx:mem
sleep 120
siege -c150 -b -t300s --log=./siege.log -q -f ~/url-list.php.txt
sleep 120


screen -dmS php56-top ~/php-top-chart.php -i 1 -t 540 -n php56 -c load_average la1 -c mem memUsed,memFree -c worker_memory php-fpm:mem -c worker_cpu php-fpm:cpu -c misc mysqld:cpu,beam.smp:cpu,redis-server:cpu,nginx:cpu -c misc_mem mysqld:mem,beam.smp:mem,redis-server:mem,nginx:mem
sleep 120
siege -c150 -b -t300s --log=./siege.log -q  -f ~/url-list.php56.txt
sleep 120


~/shop.dev/php-top-chart.php -n merged -m hhvm,php54,php56