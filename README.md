# Local WordPress development

## Files

```text
wordpress-container/
|-- .env.example       # Safe-to-commit environment template
|-- .env               # Local credentials; ignored by Git
|-- .gitignore
|-- .dockerignore
|-- Dockerfile         # Builds the local WordPress image
|-- docker-compose.yml
|-- README.md
```

## Configure and start

1. Copy `.env.example` to `.env`.
2. Replace both password placeholders with different, long random values. Keep `.env` private.
3. Start the services:

   ```powershell
   docker compose up -d --build
   ```

4. Open <http://localhost:8080>.

The `database` service uses the database name and user from `.env`. Its `MYSQL_PASSWORD` and WordPress's `WORDPRESS_DB_PASSWORD` are sourced from the same `MYSQL_PASSWORD` value so they stay in sync.

## Manage the environment

```powershell
# View service status and follow logs
docker compose ps
docker compose logs -f

# Stop containers without removing them or their data
docker compose stop

# Start stopped containers again
docker compose start

# Remove containers and the Compose network; preserve named volumes and data
docker compose down

# Remove the environment and permanently delete WordPress and database data
docker compose down --volumes
```

## GitHub Actions deployment

The workflow at `.github/workflows/compose-ci-cd.yml` validates the Compose configuration and builds the WordPress image on pushes and pull requests. A push to `main` deploys the stack to a remote Docker host over SSH.

### Prepare the server

Install Docker Engine with the Compose plugin and configure SSH access for a deployment user that can run Docker. In the user's home directory, create `wordpress-container/.env` with the same variables as `.env.example`; protect it with `chmod 600 wordpress-container/.env`. The workflow copies `docker-compose.yml` and `Dockerfile`; credentials stay on the server. Ensure the server firewall permits inbound SSH.

The Compose port mapping binds WordPress to `127.0.0.1:8080`, including on the server. To serve it publicly, configure a host reverse proxy with TLS to forward traffic to that loopback address; do not expose the WordPress container port directly unless you intentionally change the port binding and firewall rules.

### Configure GitHub Actions secrets

Add these repository secrets under **Settings → Secrets and variables → Actions**:

| Secret | Value |
| --- | --- |
| `DEPLOY_HOST` | Server hostname or IP address |
| `DEPLOY_USER` | SSH deployment username |
| `DEPLOY_SSH_KEY` | Private key (preferably Ed25519) for that user's SSH access |
| `DEPLOY_KNOWN_HOSTS` | Verified SSH host-key line for the server |

Obtain the host key separately and verify its fingerprint through a trusted channel before saving it. Do not disable SSH host-key checking or use an unverified `ssh-keyscan` result.

After a validated push to `main`, the workflow copies the Compose and build files to `~/wordpress-container`, pulls the MySQL image, builds the WordPress image on the server, and recreates services as needed. Pull requests and pushes to other branches run validation and build checks only.

## Security and development notes

- The Dockerfile builds the local WordPress image on top of the official `wordpress:latest` image; MySQL uses `mysql:latest`. Add site-specific PHP extensions or configuration to the Dockerfile as needed.
- Credentials are kept in the untracked `.env` file; only the placeholder `.env.example` should be committed. Never put real credentials in source control.
- WordPress is published only on the host's loopback interface (`127.0.0.1:8080`). MySQL has no published host port and is reachable only by WordPress over the private Compose network.
- Separate named volumes persist the WordPress files and MySQL data across container recreation.
- The database health check gates WordPress startup until MySQL is ready. `unless-stopped` restarts containers after failures while allowing you to intentionally stop them.
- `latest` tags track upstream releases and can change on a later pull. For reproducible team environments, agree on explicit image versions and update them deliberately.
- The Compose stack is intended for local development or a controlled development server, not production deployment. Do not expose it to an untrusted network.
