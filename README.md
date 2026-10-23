# WordPress container

Local WordPress development with Docker Compose, using the official WordPress and MySQL images.

## Repository layout

```text
wordpress-container/
|-- .env.example
|-- .github/
|   `-- workflows/
|       `-- compose-ci-cd.yml
|-- .gitignore
|-- docker-compose.yml
|-- wp-content/        # Local themes, plugins, and uploads, mounted into WordPress
`-- README.md
```

Create a local `.env` from `.env.example`. It is excluded from Git.

## Requirements

- Docker Desktop (Windows/macOS) or Docker Engine (Linux)
- Docker Compose v2 (`docker compose`)

## Run locally

1. Copy `.env.example` to `.env` and replace the two password placeholders with different, long random passwords.

   ```powershell
   Copy-Item .env.example .env
   ```

2. Start the services:

   ```powershell
   docker compose up -d
   ```

3. Finish WordPress setup at <http://localhost:8080>.

Compose uses the official `wordpress:latest` and `mysql:latest` images. WordPress waits for the database health check before starting.

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

## Develop with `wp-content`

The repository's `wp-content/` folder is bind-mounted at `/var/www/html/wp-content`. Add or edit themes, plugins, and other content there; changes are available in the running container immediately. The folder is tracked by Git, so commit only content you intend to share.

### Add a theme

1. Create a theme folder under `wp-content/themes/` and put the theme files in it. A theme needs at least a `style.css` stylesheet with a valid WordPress theme header and an `index.php` template.

   ```powershell
   New-Item -ItemType Directory -Force .\wp-content\themes\my-theme
   # Copy or create the theme files in .\wp-content\themes\my-theme
   ```

2. Open <http://localhost:8080/wp-admin>, sign in, then go to **Appearance → Themes** and activate the theme.
3. Commit the theme files you want to keep in source control. With the bind mount active, file edits appear in the container immediately; no image rebuild is needed.

### Add a plugin

1. Create a folder under `wp-content/plugins/` and put the plugin PHP files in it. The main PHP file should contain a valid WordPress plugin header.

   ```powershell
   New-Item -ItemType Directory -Force .\wp-content\plugins\my-plugin
   # Copy or create the plugin files in .\wp-content\plugins\my-plugin
   ```

2. Open <http://localhost:8080/wp-admin>, sign in, then go to **Plugins → Installed Plugins** and activate the plugin.
3. Commit the plugin files you want to keep in source control. Changes appear immediately through the bind mount; no image rebuild is required.

You can also install a theme or plugin from the WordPress dashboard. Because `wp-content/` is bind-mounted, WordPress writes those files into the repository folder. Check the folder permissions if WordPress cannot write or update files. Treat third-party themes and plugins as executable code: use trusted sources and review what you add.

The named `wordpress_data` volume still persists the rest of the WordPress installation. The host `wp-content` folder takes precedence over the copy at that path in the volume. If you already have an existing WordPress volume with content, export it before recreating the service with the bind mount; otherwise the mount will hide the volume's existing `wp-content` files:

```powershell
New-Item -ItemType Directory -Force .\wp-content
docker compose cp wordpress:/var/www/html/wp-content/. .\wp-content
docker compose up -d --force-recreate wordpress
```

For a fresh install the repository folder is already present. If WordPress cannot write uploads or plugin files, check the host folder permissions. The deployment workflow transfers this folder to the server as well.

## GitHub Actions CI/CD

The workflow at `.github/workflows/compose-ci-cd.yml`:

- Validates the Compose configuration on pushes and pull requests.
- Deploys only on a push to `main`, after validation succeeds.
- Connects to a remote Docker host over SSH, transfers `docker-compose.yml` and `wp-content/`, pulls the configured images, and applies the Compose stack.

### Set up the deployment server

Install Docker Engine with the Compose plugin. Configure SSH access for a deployment user with permission to run Docker. In that user's home directory, create `wordpress-container/.env` with the variables listed above and protect it:

```sh
chmod 600 ~/wordpress-container/.env
```

The workflow does not copy `.env`; server credentials remain on the server. It transfers `docker-compose.yml` and `wp-content`. The Compose file binds WordPress to `127.0.0.1:8080`, so it is not directly exposed to the internet. To serve it remotely, configure a TLS-enabled reverse proxy on the server to forward requests to that loopback address.

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
- WordPress runs directly from the official image; add image customization only if the project later needs custom PHP extensions or system packages.
- `docker compose down` preserves the volumes. `docker compose down --volumes` deletes them and their data.
- The `latest` image tags can refer to newer releases after a future pull. Pin versions when reproducible deployments are needed.
- This setup is for local development or a controlled development server, not production deployment.
