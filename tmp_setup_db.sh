#!/bin/bash
docker exec mysql_central mysql -uroot -p'R00t_decccbeda526fb3f06916ef4' -e "CREATE DATABASE IF NOT EXISTS ai_reply CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker exec mysql_central mysql -uroot -p'R00t_decccbeda526fb3f06916ef4' -e "CREATE USER IF NOT EXISTS 'ai_reply'@'%' IDENTIFIED BY 'AiReply@2026';"
docker exec mysql_central mysql -uroot -p'R00t_decccbeda526fb3f06916ef4' -e "GRANT ALL PRIVILEGES ON ai_reply.* TO 'ai_reply'@'%'; FLUSH PRIVILEGES;"
docker exec mysql_central mysql -uroot -p'R00t_decccbeda526fb3f06916ef4' -e "SHOW DATABASES;"
echo "DB SETUP DONE"
