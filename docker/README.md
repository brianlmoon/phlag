# Phlag Docker Deployment

This directory contains Docker deployment files for the Phlag feature flag management system.

## Architecture

- **Base Image**: Phusion Baseimage (Ubuntu 22.04 LTS)
- **Services**: Nginx + PHP 8.4-FPM (managed by runit)
- **Process Manager**: Runit (built into Phusion baseimage)
- **Document Root**: `/app/public`

## Quick Start

### Using Docker Compose (Recommended for Development)

```bash
# Start the stack
docker compose up -d

# Initialize the database
docker compose exec mysql mysql -uroot -pphlag_root_pass phlag < schema/mysql.sql

# Access the application
open http://localhost:8000

# Create your first user
open http://localhost:8000/first-user

# View logs
docker-compose logs -f phlag

# Stop the stack
docker-compose down
```

### Using Docker CLI (Production)

```bash
# Build the image
docker build -t phlag:latest .

# Run with environment variables
docker run -d \
  --name phlag \
  -p 8000:80 \
  -e DB_PHLAG_TYPE=mysql \
  -e DB_PHLAG_HOST=db.example.com \
  -e DB_PHLAG_PORT=3306 \
  -e DB_PHLAG_DB=phlag \
  -e DB_PHLAG_USER=phlag_user \
  -e DB_PHLAG_PASS=secret \
  -e MAILER_FROM_ADDRESS=noreply@example.com \
  phlag:latest

# Run with volume-mounted config
docker run -d \
  --name phlag \
  -p 8000:80 \
  -v /path/to/config.ini:/app/etc/config.ini:ro \
  phlag:latest
```

## Environment Variables

### Database Configuration (DB_PHLAG_ Prefix)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_PHLAG_TYPE` | Yes | - | Database type (`mysql`, `pgsql`, or `sqlite`) |
| `DB_PHLAG_HOST` | Yes* | - | Database server hostname (*not required for SQLite) |
| `DB_PHLAG_PORT` | No | 3306/5432 | Database server port (auto-detected based on type) |
| `DB_PHLAG_DB` | Yes | - | Database name (or path for SQLite) |
| `DB_PHLAG_USER` | No | - | Database username |
| `DB_PHLAG_PASS` | No | - | Database password |

### Email Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MAILER_FROM_ADDRESS` | No | - | Email sender address (required for password reset) |
| `MAILER_FROM_NAME` | No | `Phlag Admin` | Email sender name |
| `MAILER_METHOD` | No | `mail` | Email method (`smtp` or `mail`) |
| `SMTP_HOST` | No* | - | SMTP server hostname (*required if `MAILER_METHOD=smtp`) |
| `SMTP_PORT` | No | `587` | SMTP server port |
| `SMTP_ENCRYPTION` | No | `tls` | SMTP encryption (`tls` or `ssl`) |
| `SMTP_USERNAME` | No | - | SMTP authentication username |
| `SMTP_PASSWORD` | No | - | SMTP authentication password |

### Application Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SESSION_TIMEOUT` | No | `1800` | Session timeout in seconds (30 minutes) |
| `PHLAG_BASE_URL_PATH` | No | - | Base URL path for subdirectory installs (e.g., `/phlag`) |

## Volume Mounts

### Configuration (Recommended)

Mount a custom `config.ini` file to `/app/etc/config.ini`:

```bash
docker run -d \
  -v /path/to/config.ini:/app/etc/config.ini:ro \
  phlag:latest
```

**Note**: Volume-mounted config takes precedence over environment variables.

### Logs (Optional)

```bash
docker run -d \
  -v phlag-nginx-logs:/var/log/nginx \
  -v phlag-php-logs:/var/log/php \
  phlag:latest
```

## File Structure

```
docker/
├── nginx/
│   └── phlag.conf              # Nginx site configuration
├── php-fpm/
│   └── pool.conf               # PHP-FPM pool configuration
├── runit/
│   ├── nginx/
│   │   └── run                 # Nginx runit service
│   └── php-fpm/
│       └── run                 # PHP-FPM runit service
└── scripts/
    └── 01_setup_config.sh      # Startup configuration script
```

## Database Initialization

### MySQL

```bash
# Using Docker Compose
docker-compose exec mysql mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} < schema/mysql.sql

# Using standalone container
docker exec -i phlag-mysql mysql -uroot -psecret phlag < schema/mysql.sql
```

### PostgreSQL

```bash
# Using standalone container
docker exec -i phlag-postgres psql -U phlag_user -d phlag < schema/pgsql.sql
```

### SQLite

```bash
# Copy schema to container and initialize
docker cp schema/sqlite.sql phlag:/tmp/
docker exec phlag sqlite3 /app/data/phlag.db < /tmp/sqlite.sql
```

## Healthcheck

The container includes basic HTTP healthchecking:

```bash
# Check container health
docker inspect --format='{{.State.Health.Status}}' phlag
```

## Troubleshooting

### View Logs

```bash
# Using Docker Compose
docker-compose logs -f phlag

# Using Docker CLI
docker logs -f phlag
```

### Access Container Shell

```bash
# Using Docker Compose
docker-compose exec phlag bash

# Using Docker CLI
docker exec -it phlag bash
```

### Verify Configuration

```bash
# Check generated config.ini
docker exec phlag cat /app/etc/config.ini

# Verify database connection
docker exec phlag php -r "require '/app/vendor/autoload.php'; \$repo = \Moonspot\Phlag\Data\Repository::init(); echo 'Connected successfully';"
```

### Common Issues

**Database connection failed**
- Verify `DB_PHLAG_HOST` is reachable from container
- Check database credentials
- Ensure database exists and schema is initialized

**Permission denied**
- Ensure volume-mounted files are readable by `www-data` (UID 33)
- Check file permissions: `chmod 644 config.ini`

**Nginx/PHP-FPM not starting**
- Check logs: `docker logs phlag`
- Verify runit services: `docker exec phlag sv status /etc/service/*`

## Production Deployment

### Kubernetes

Example deployment with MySQL:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: phlag
spec:
  replicas: 2
  selector:
    matchLabels:
      app: phlag
  template:
    metadata:
      labels:
        app: phlag
    spec:
      containers:
      - name: phlag
        image: phlag:latest
        ports:
        - containerPort: 80
        env:
        - name: DB_PHLAG_TYPE
          value: "mysql"
        - name: DB_PHLAG_HOST
          value: "mysql-service"
        - name: DB_PHLAG_DB
          value: "phlag"
        - name: DB_PHLAG_USER
          valueFrom:
            secretKeyRef:
              name: phlag-db-secret
              key: username
        - name: DB_PHLAG_PASS
          valueFrom:
            secretKeyRef:
              name: phlag-db-secret
              key: password
        volumeMounts:
        - name: config
          mountPath: /app/etc/config.ini
          subPath: config.ini
          readOnly: true
      volumes:
      - name: config
        configMap:
          name: phlag-config
```

### Docker Swarm

```bash
docker service create \
  --name phlag \
  --replicas 3 \
  --publish 8000:80 \
  --env DB_PHLAG_TYPE=mysql \
  --env DB_PHLAG_HOST=mysql \
  --env DB_PHLAG_DB=phlag \
  --secret source=db_user,target=DB_PHLAG_USER \
  --secret source=db_pass,target=DB_PHLAG_PASS \
  phlag:latest
```

## Security Considerations

1. **Don't commit secrets**: Use environment variables or mounted secrets
2. **Use read-only volumes**: Mount config.ini as read-only (`:ro`)
3. **Network isolation**: Use Docker networks to isolate services
4. **HTTPS**: Use reverse proxy (Traefik, Nginx) for TLS termination
5. **Database access**: Restrict database network access to Phlag containers only
6. **Regular updates**: Keep base image and PHP packages updated

## License

BSD 3-Clause License - Copyright (c) 2025, Brian Moon
