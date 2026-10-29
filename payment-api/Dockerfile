FROM php:8.3-cli-alpine
WORKDIR /app
COPY api.php /app/api.php
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s CMD wget -q -O /dev/null http://127.0.0.1:8080/api.php/charges || exit 0
# docroot = /app, so the endpoint is  POST /api.php/charges
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
