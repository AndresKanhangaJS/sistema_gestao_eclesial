#!/usr/bin/env bash
# Deploy do SGE em producao. Correr na raiz do projecto, no servidor.
#
#   ./deploy.sh              deploy normal (git pull, build, migrate, restart)
#   ./deploy.sh --no-pull    nao faz git pull (usa o codigo que ja esta no disco)
#   ./deploy.sh --seed       corre tambem db:seed --force (so no 1.o deploy)
#
# Requer: docker compose v2, git, e um .env de producao na raiz.
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.prod.yml"
PULL=1
SEED=0
for arg in "$@"; do
    case "$arg" in
        --no-pull) PULL=0 ;;
        --seed) SEED=1 ;;
        *) echo "Opcao desconhecida: $arg" >&2; exit 1 ;;
    esac
done

passo() { printf '\n==> %s\n' "$1"; }
erro() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }

# --- 1. Validacoes do .env ---
passo "A validar .env"
[ -f .env ] || erro ".env nao existe. Copie .env.example e preencha com valores de producao."
valor() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | tr -d '"'; }

[ "$(valor APP_ENV)" = "production" ] || erro "APP_ENV tem de ser 'production'."
[ "$(valor APP_DEBUG)" = "false" ] || erro "APP_DEBUG tem de ser 'false'."
[ -n "$(valor APP_KEY)" ] || erro "APP_KEY vazio. Gere com: php artisan key:generate --show"
[ "$(valor DB_PASSWORD)" != "sge_pass" ] || erro "DB_PASSWORD ainda e o valor de desenvolvimento."
[ -n "$(valor DB_ROOT_PASSWORD)" ] || erro "DB_ROOT_PASSWORD em falta no .env."
case "$(valor APP_URL)" in
    https://*) ;;
    *) echo "AVISO: APP_URL nao comeca por https://" ;;
esac

# --- 2. Codigo ---
if [ "$PULL" -eq 1 ]; then
    passo "A actualizar o codigo (git pull --ff-only)"
    git pull --ff-only
fi
export IMAGE_TAG="${IMAGE_TAG:-$(git rev-parse --short HEAD)}"
echo "Versao: $IMAGE_TAG"

# --- 3. Backup da BD (se ja estiver a correr) ---
if $COMPOSE ps --status running --services 2>/dev/null | grep -qx mysql; then
    passo "Backup da base de dados"
    mkdir -p backups
    FICHEIRO="backups/sge_$(date +%F_%H%M%S).sql.gz"
    $COMPOSE exec -T mysql sh -c \
        'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
        | gzip > "$FICHEIRO"
    echo "Guardado em $FICHEIRO"
    # mantem os 14 backups mais recentes
    ls -1t backups/sge_*.sql.gz 2>/dev/null | tail -n +15 | xargs -r rm --
else
    echo "MySQL ainda nao esta a correr (primeiro deploy?), sem backup."
fi

# --- 4. Build das imagens ---
passo "A construir as imagens"
$COMPOSE build

# --- 5. Infra + migrations (com a imagem nova, antes de trocar a app) ---
passo "A subir MySQL e Redis"
$COMPOSE up -d --wait mysql redis

passo "Migrations"
$COMPOSE run --rm --no-deps app php artisan migrate --force

if [ "$SEED" -eq 1 ]; then
    passo "Seeders"
    $COMPOSE run --rm --no-deps app php artisan db:seed --force
fi

# --- 6. Trocar os servicos ---
passo "A subir a aplicacao"
$COMPOSE up -d --remove-orphans

passo "Caches do Laravel"
$COMPOSE exec -T app php artisan optimize:clear
$COMPOSE exec -T app php artisan optimize
$COMPOSE exec -T app php artisan filament:optimize || true
$COMPOSE exec -T app php artisan queue:restart

# --- 7. Verificacao ---
passo "Verificacao"
$COMPOSE ps
PORTA="$(valor HTTP_PORT)"; PORTA="${PORTA:-8080}"
if curl -fsS -o /dev/null --max-time 15 "http://127.0.0.1:${PORTA}/up"; then
    echo "Aplicacao a responder em /up (porta ${PORTA})."
else
    erro "A aplicacao nao respondeu em /up. Veja: $COMPOSE logs --tail=100 app nginx"
fi

# Limpa imagens antigas sem uso
docker image prune -f >/dev/null

passo "Deploy concluido (versao $IMAGE_TAG)"
