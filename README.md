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

Because the bind mount replaces WordPress's default `/wp-content` tree, a minimal fallback theme is kept in `wp-content/themes/twentytwentyfive/` so the site does not render as a blank page when WordPress tries to use its default bundled theme.

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

The workflow at `.github/workflows/compose-ci-cd.yml` validates Compose on pushes and pull requests. A validated push to `main` deploys automatically; you can also start a deployment manually from the Actions tab on `main`. Deployment connects to the server over SSH, pulls `origin/main` and the latest images, then runs `docker compose down` followed by `docker compose up -d --remove-orphans`.

### Step-by-step setup

1. **Prepare the server.** Install Git, Docker Engine, and the Docker Compose plugin. Create a deployment user with SSH access and permission to run Docker. Restrict inbound port `8080` with the server firewall or configure a TLS-enabled reverse proxy in front of it.

2. **Clone the repository on the server.** As the deployment user, clone the repository to the exact path expected by the workflow:

   ```sh
   git clone <repository-url> ~/wordpress-container
   cd ~/wordpress-container
   ```

   The checkout must use `origin` for the GitHub remote and `main` as the deployment branch. For a private repository, give this server a separate read-only GitHub deploy key or another non-interactive Git credential so `git pull origin main` can authenticate. The SSH key used by Actions to log in to the server does not provide Git access from the server to GitHub.

3. **Create the server environment file.** Create `~/wordpress-container/.env` with the values described in [Configuration](#configuration), using unique passwords. Do not commit or copy this file from GitHub Actions. Restrict its permissions:

   ```sh
   chmod 600 ~/wordpress-container/.env
   ```

4. **Create an SSH key for GitHub Actions.** On a trusted machine, generate a dedicated key pair for Actions-to-server access:

   ```sh
   ssh-keygen -t ed25519 -C github-actions-wordpress-deploy -f github-actions-wordpress -N ""
   ```

   Add the contents of `github-actions-wordpress.pub` to the deployment user's `~/.ssh/authorized_keys` on the server. Keep the private key file secret; you will add its contents to GitHub in the next step. Test SSH access with the key before configuring Actions.

5. **Get and verify the server's SSH host key.** This is the server's public identity, not the Actions login key from Step 4. The workflow checks it against `DEPLOY_KNOWN_HOSTS` before connecting, which helps prevent connecting to an impersonated server.

   On the server (or through its trusted console), have an administrator print the SHA-256 fingerprint of its Ed25519 SSH host key:

   ```sh
   sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
   ```

   On a trusted machine, collect the public key line for the exact hostname or IP address you will put in `DEPLOY_HOST` (this example assumes SSH port 22):

   ```sh
   ssh-keyscan -t ed25519 -p 22 your-server.example.com > server_known_hosts
   ssh-keygen -lf server_known_hosts -E sha256
   ```

   Compare the `SHA256:...` fingerprint from this output with the fingerprint obtained through the trusted server console or administrator. They must match exactly. `ssh-keyscan` only collects a key; by itself it does not verify that key belongs to your server. If the fingerprints do not match, stop and confirm the hostname, IP, and key with the administrator rather than adding the entry.

   Once verified, copy the complete line from `server_known_hosts` into the `DEPLOY_KNOWN_HOSTS` repository secret in Step 6. Its hostname/IP must match `DEPLOY_HOST`. Keep the host key public; do not confuse it with or upload the private login key.

6. **Add GitHub repository secrets.** Open the repository's **Settings → Secrets and variables → Actions**, choose **New repository secret**, and add each of the following:

   | Secret | Value |
   | --- | --- |
   | `DEPLOY_HOST` | Server hostname or IP address |
   | `DEPLOY_USER` | SSH deployment username |
   | `DEPLOY_SSH_KEY` | Entire contents of the private `github-actions-wordpress` key |
   | `DEPLOY_KNOWN_HOSTS` | Verified known-hosts entry for the server |

7. **Deploy and verify.** Push a change to `main`. In the repository's **Actions** tab, open the **WordPress Compose CI/CD** run. Confirm validation and deployment succeed, then check the server with `docker compose -f ~/wordpress-container/docker-compose.yml --env-file ~/wordpress-container/.env ps` and visit the site.

   To deploy manually, select **WordPress Compose CI/CD**, choose **Run workflow**, select `main`, and start the run. Pull requests run validation only; they do not deploy.

`docker compose down` does not remove named volumes, so WordPress and MySQL data are retained. Deployment causes a brief service interruption while containers are recreated. Keep the server checkout clean: `git pull --ff-only` will fail if local tracked files have uncommitted changes or the local branch has diverged from `origin/main`. In particular, take care that runtime uploads or dashboard-installed content do not modify tracked files in `wp-content/`.

## Security and data notes

- MySQL has no published host port; service traffic uses the private Compose network.
- WordPress is published on host port `8080`; restrict access with the server firewall or a TLS-enabled reverse proxy.
- Named volumes persist WordPress files and MySQL data when containers are recreated.
- WordPress runs directly from the official image; add image customization only if the project later needs custom PHP extensions or system packages.
- `docker compose down` preserves the volumes. `docker compose down --volumes` deletes them and their data.
- The `latest` image tags can refer to newer releases after a future pull. Pin versions when reproducible deployments are needed.
- This setup is for local development or a controlled development server, not production deployment.
