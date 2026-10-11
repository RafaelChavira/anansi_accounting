#!/bin/bash
# Crea el ambiente de testing a partir de produccion. Ejecutar en el server como root:
#   bash /opt/docker/projects/anansi_accounting/docker/test/setup-test.sh
# Es idempotente en lo posible y NO modifica la BD ni los archivos de produccion (solo lectura).
set -euo pipefail

PROD_DIR=/opt/docker/projects/anansi_accounting
TEST_DIR=/opt/docker/projects/anansi_accounting_test
TEST_DOMAIN=test.sistemapararestaurantes.com
PROD_DB=costeo
TEST_DB=costeo_test
TEST_DB_USER=costeo_test
MYSQL_CONTAINER=mysql

read -r -s -p "Password root de MySQL: " MYSQL_ROOT_PASSWORD; echo
read -r -s -p "Password NUEVO para el usuario ${TEST_DB_USER}: " TEST_DB_PASSWORD; echo

echo "== 1. Clonar proyecto =="
if [ ! -d "$TEST_DIR" ]; then
  git clone "$(git -C "$PROD_DIR" remote get-url origin)" "$TEST_DIR"
fi
cd "$TEST_DIR"
git fetch origin
git checkout production
git pull --ff-only origin production
git checkout -B testing

echo "== 2. Copiar configuracion local, vendor y archivos subidos de prod =="
for f in api/config/main-local.php api/config/params-local.php \
         backend/config/main-local.php backend/config/params-local.php \
         frontend/config/main-local.php frontend/config/params-local.php \
         landing/config/main-local.php landing/config/params-local.php \
         common/config/main-local.php common/config/params-local.php \
         console/config/main-local.php console/config/params-local.php; do
  [ -f "$PROD_DIR/$f" ] && cp -a "$PROD_DIR/$f" "$TEST_DIR/$f"
done
[ -d "$TEST_DIR/vendor" ] || cp -a "$PROD_DIR/vendor" "$TEST_DIR/vendor"
mkdir -p backend/web/uploads
cp -an "$PROD_DIR/backend/web/uploads/." backend/web/uploads/ || true

echo "== 3. Ajustar config a TEST =="
# BD de test y usuario propio (sin acceso a la BD de produccion)
sed -i "s#dbname=${PROD_DB}#dbname=${TEST_DB}#" common/config/main-local.php
sed -i "s#'username' => 'costeo',#'username' => '${TEST_DB_USER}',#" common/config/main-local.php
sed -i "0,/'password' => '[^']*',/s##'password' => '${TEST_DB_PASSWORD}',#" common/config/main-local.php
# Correo: NO enviar, guardar en backend/runtime/mail
grep -q useFileTransport common/config/main-local.php || \
  sed -i "s#\('class' => \\\\yii\\\\symfonymailer\\\\Mailer::class,\)#\1\n            'useFileTransport' => true,#" common/config/main-local.php
# Stripe: vaciar llaves de produccion (poner llaves sk_test_/pk_test_/whsec_ de TEST a mano)
sed -i -E "s#('stripe\.(secretKey|publicKey|webhook)' => )[^,]+,#\1\"\",#" common/config/params-local.php
sed -i -E "s#('backendBaseUrl' => )[^,]+,#\1\"https://${TEST_DOMAIN}\",#" common/config/params-local.php

echo "== 4. Crear BD de test y copiar datos de produccion =="
MYSQL() { docker exec -i -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" "$MYSQL_CONTAINER" mysql -uroot "$@"; }
MYSQL -e "CREATE DATABASE IF NOT EXISTS ${TEST_DB} CHARACTER SET utf8mb4;"
MYSQL -e "CREATE USER IF NOT EXISTS '${TEST_DB_USER}'@'%' IDENTIFIED BY '${TEST_DB_PASSWORD}';
          ALTER USER '${TEST_DB_USER}'@'%' IDENTIFIED BY '${TEST_DB_PASSWORD}';
          GRANT ALL PRIVILEGES ON ${TEST_DB}.* TO '${TEST_DB_USER}'@'%'; FLUSH PRIVILEGES;"
docker exec -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" "$MYSQL_CONTAINER" \
  mysqldump -uroot --single-transaction --routines --triggers "$PROD_DB" \
  | MYSQL "$TEST_DB"

echo "== 5. Levantar contenedor =="
docker compose -f docker-compose.test.yml up -d --build

echo "== 6. Migraciones pendientes =="
docker exec backend_costeo_test php yii migrate --interactive=0

cat <<EOF

LISTO. Pendiente a mano:
  - Poner llaves de TEST de Stripe en ${TEST_DIR}/common/config/params-local.php
  - DNS: registro A de ${TEST_DOMAIN} -> IP del server
  - Anonimizar emails de usuarios en ${TEST_DB} si hay riesgo de enviar correos (el mailer ya esta en file-transport)
  - NO copiar los crons de backup al ambiente de test
EOF
