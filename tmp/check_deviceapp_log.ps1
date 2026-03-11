$remoteCommand = "docker compose -f /opt/ai_reply/docker-compose.yml exec -T ai_reply_api sh -lc 'grep -n \"\\[DeviceApp\\]\" storage/logs/laravel.log | tail -30'"
ssh root@207.180.228.84 $remoteCommand
