FROM redis:7-alpine

RUN mkdir -p /run/redis-socket && chmod 777 /run/redis-socket
COPY ./redis.conf /etc/redis.conf
CMD ["redis-server", "/etc/redis.conf"]
