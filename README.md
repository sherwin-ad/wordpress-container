# WordPress container

Local WordPress development with Docker Compose, using an image built from the official WordPress image and a MySQL database.

## Repository layout

```text
wordpress-container/
|-- .dockerignore
|-- .env.example
|-- .github/
|   `-- workflows/
|       `-- compose-ci-cd.yml
|-- .gitignore
|-- Dockerfile
|-- docker-compose.yml
|-- wp-content/        # Local themes, plugins, and uploads, mounted into WordPress
`-- README.md
```

Create a local `.env` from `.env.example`. It is excluded from Git and from the Docker build context.

## Requirements

- Docker Desktop (Windows/macOS) or Docker Engine (Linux)
- Docker Compose v2 (`docker compose`)

## Run locally

1. Copy `.env.example` to `.env` and replace the two password placeholders with different, long random passwords.

   ```powershell
   Copy-Item .env.example .env
   ```

2. Build the WordPress image and start the services:

   ```powershell
   docker compose up -d --build
   ```

3. Finish WordPress setup at <http://localhost:8080>.

Compose builds `local-wordpress:latest` from `Dockerfile`, which currently extends the official `wordpress:latest` image. MySQL uses `mysql:latest`. WordPress waits for the database health check before starting.

### Configuration

The `.env` file supplies:

| Variable | Purpose |
| --- | --- |
| `MYSQL_ROOT_PASSWORD` | MySQL administrative password |
| `WORDPRESS_DB_NAME` | Database created for WordPress |
| `WORDPRESS_DB_USER` | Database user created for WordPress |
| `MYSQL_PASSWORD` | Password for the WordPress database user |

The same `MYSQL_PASSWORD` value is passed to MySQL and WordPress so their database credentials match. Keep `.env` private; do not commit it.

### Common commands

```powershell
# Check service status and follow logs
docker compose ps
docker compose logs -f

# Stop containers; preserve containers and data
docker compose stop

# Restart stopped containers
docker compose start

# Remove containers and network; preserve named volumes and data
docker compose down

# Also permanently delete WordPress and MySQL data
docker compose down --volumes
```

## Customize the WordPress image

Add required PHP extensions, system packages, or image configuration to `Dockerfile`, then rebuild:

```powershell
docker compose up -d --build wordpress
```

The `.dockerignore` file keeps `.env`, Git metadata, and workflow files out of the image build context.

## Develop with `wp-content`

The repository's `wp-content/` folder is bind-mounted at `/var/www/html/wp-content`. Add or edit themes, plugins, and other content there; changes are available in the running container immediately. The folder is tracked by Git, so commit only content you intend to share.

The Dockerfile also copies `wp-content/` into the built image. During local Compose runs, the bind mount overlays that image directory, so the local folder remains the source of truth.

The named `wordpress_data` volume still persists the rest of the WordPress installation. The host `wp-content` folder takes precedence over the copy at that path in the volume. If you already have an existing WordPress volume with content, export it before recreating the service with the bind mount; otherwise the mount will hide the volume's existing `wp-content` files:

```powershell
New-Item -ItemType Directory -Force .\wp-content
docker compose cp wordpress:/var/www/html/wp-content/. .\wp-content
docker compose up -d --force-recreate wordpress
```

For a fresh install the repository folder is already present. If WordPress cannot write uploads or plugin files, check the host folder permissions. The deployment workflow transfers this folder to the server as well.

## GitHub Actions CI/CD

The workflow at `.github/workflows/compose-ci-cd.yml`:

- Validates the Compose configuration and builds the WordPress image on pushes and pull requests.
- Deploys only on a push to `main`, after validation succeeds.
- Connects to a remote Docker host over SSH, transfers `docker-compose.yml`, `Dockerfile`, and `wp-content/`, pulls the MySQL image, builds WordPress on the server, and applies the Compose stack.

### Set up the deployment server

Install Docker Engine with the Compose plugin. Configure SSH access for a deployment user with permission to run Docker. In that user's home directory, create `wordpress-container/.env` with the variables listed above and protect it:

```sh
chmod 600 ~/wordpress-container/.env
```

The workflow does not copy `.env`; server credentials remain on the server. It transfers `docker-compose.yml`, `Dockerfile`, and `wp-content`. The Compose file binds WordPress to `127.0.0.1:8080`, so it is not directly exposed to the internet. To serve it remotely, configure a TLS-enabled reverse proxy on the server to forward requests to that loopback address.

### Add repository secrets

In **Settings → Secrets and variables → Actions**, add:

| Secret | Value |
| --- | --- |
| `DEPLOY_HOST` | Server hostname or IP address |
| `DEPLOY_USER` | SSH deployment username |
| `DEPLOY_SSH_KEY` | Private SSH key for that user |
| `DEPLOY_KNOWN_HOSTS` | Verified SSH host-key line for the server |

Verify the server host-key fingerprint through a trusted channel before saving it. Do not disable SSH host-key checking or rely on an unverified host key.

## Security and data notes

- MySQL has no published host port; service traffic uses the private Compose network.
- WordPress is published only on the host's loopback interface, on port `8080`.
- Named volumes persist WordPress files and MySQL data when containers are recreated.
- `docker compose down` preserves the volumes. `docker compose down --volumes` deletes them and their data.
- The `latest` image tags can refer to newer releases after a future pull. Pin versions when reproducible deployments are needed.
- This setup is for local development or a controlled development server, not production deployment.
