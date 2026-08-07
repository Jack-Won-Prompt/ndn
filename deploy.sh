#!/usr/bin/env bash
#
# NDN 운영 배포 스크립트 (git pull 방식)
# ---------------------------------------------------------------------------
# 사용법:
#   bash deploy.sh
#
# 환경변수로 조정 가능:
#   PHP=php            실행할 PHP 바이너리 (기본 php)   예) PHP=/usr/bin/php8.2
#   COMPOSER=composer  composer 실행 커맨드 (기본 composer)
#   BRANCH=main        배포할 브랜치 (기본 main)
#   BUILD_ASSETS=0     1 이면 npm ci && npm run build 수행 (기본 0: 정적 에셋이라 불필요)
#
# ※ 최초 배포(폴더 없음/클론)는 이 스크립트가 아니라 초기 설치 절차를 따르세요.
#   (git clone → composer install → .env → key:generate → migrate --force
#    → db:seed --force → ndn:create-admin)
# ---------------------------------------------------------------------------
set -euo pipefail

# 스크립트가 있는 디렉터리(=프로젝트 루트)로 이동
cd "$(dirname "$0")"

PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
BRANCH="${BRANCH:-main}"
BUILD_ASSETS="${BUILD_ASSETS:-0}"

log()  { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

# --- 사전 점검 ---------------------------------------------------------------
[ -f artisan ]   || die "artisan 이 없습니다. 프로젝트 루트에서 실행하세요."
[ -f .env ]      || die ".env 가 없습니다. 최초 설치 절차를 먼저 수행하세요."
[ -d .git ]      || die "git 저장소가 아닙니다."
command -v git >/dev/null 2>&1 || die "git 이 설치되어 있지 않습니다."

PREV_REF="$(git rev-parse --short HEAD)"

# 오류가 나거나 중단돼도 마지막에 반드시 점검 모드를 해제한다
finish() { "$PHP" artisan up >/dev/null 2>&1 || true; }
trap finish EXIT

# --- 배포 --------------------------------------------------------------------
log "점검 모드 진입 (커스텀 503 표시)"
"$PHP" artisan down --render="errors.503" --retry=15 || true

log "코드 가져오기 (origin/$BRANCH)"
git fetch --prune origin
git checkout "$BRANCH"
# 운영에는 로컬 변경이 없어야 한다. 갈라졌으면 fast-forward 실패로 안전하게 중단.
git pull --ff-only origin "$BRANCH"
NEW_REF="$(git rev-parse --short HEAD)"

log "PHP 의존성 설치"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ "$BUILD_ASSETS" = "1" ]; then
    log "프런트엔드 에셋 빌드"
    npm ci
    npm run build
fi

log "DB 마이그레이션"
"$PHP" artisan migrate --force

log "캐시 재생성 (config/route/view/event)"
"$PHP" artisan optimize

log "큐 워커 재시작"
"$PHP" artisan queue:restart || true

# 배포가 실제로 끝났는지 확인한다. 코드만 올라가고 마이그레이션이 돌지 않은 채
# 넘어가면 화면이 500·404 로 깨지는데, 그 원인이 어디에도 드러나지 않는다.
log "배포 상태 점검"
"$PHP" artisan ndn:deploy-check || die "배포가 덜 끝났습니다. 위 안내대로 처리한 뒤 다시 실행하세요."

log "점검 모드 해제"
"$PHP" artisan up
trap - EXIT   # 정상 종료: 위에서 이미 up 했으므로 트랩 해제

log "배포 완료 ✓   $PREV_REF → $NEW_REF"
echo "  롤백이 필요하면:  git reset --hard $PREV_REF  후  bash deploy.sh"
