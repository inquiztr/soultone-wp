#!/bin/bash

# --- CONFIGURATION ---
# Kamal Service Names (from your deploy.yml)
DB_CONTAINER="lumos-soultone-wp-db"
# Path on HOST where wp-content is stored (from your deploy.yml volumes)
WP_CONTENT_PATH="/var/lib/kamal/soultone/wp_data"

# S3/Minio Config
S3_BUCKET="s3://site-backups/soultone"
S3_ENDPOINT="http://miniopi:9002" # Replace with your Pi's Minio URL

# Temp Directory
BACKUP_DIR="/tmp/soultone_backups"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
mkdir -p $BACKUP_DIR

# --- 1. DATABASE BACKUP ---
echo "Backing up Database..."
# We fetch the root password dynamically from the running container to avoid hardcoding it here
DB_PASSWORD=$(docker exec $DB_CONTAINER env | grep MARIADB_ROOT_PASSWORD | cut -d= -f2)

# Dump the specific database 'soultonepickups'
docker exec -e MARIADB_ROOT_PASSWORD=$DB_PASSWORD $DB_CONTAINER \
    mariadb-dump -u root -p$DB_PASSWORD soultonepickups > "$BACKUP_DIR/db_$TIMESTAMP.sql"

# Compress the SQL
gzip "$BACKUP_DIR/db_$TIMESTAMP.sql"

# --- 2. WP-CONTENT BACKUP ---
echo "Backing up WP Content..."
# We tar the folder mounted on the host
tar -czf "$BACKUP_DIR/content_$TIMESTAMP.tar.gz" -C "$WP_CONTENT_PATH" .

# --- 3. UPLOAD TO MINIO ---
echo "Uploading DB to S3..."
# Note: We use --endpoint-url because it's Minio, not real AWS
aws --endpoint-url $S3_ENDPOINT s3 cp "$BACKUP_DIR/db_$TIMESTAMP.sql.gz" "$S3_BUCKET/"
echo "Uploading WP Content to S3..."
aws --endpoint-url $S3_ENDPOINT s3 cp "$BACKUP_DIR/content_$TIMESTAMP.tar.gz" "$S3_BUCKET/"

# --- 4. CLEANUP ---
rm -rf $BACKUP_DIR
echo "Backup Complete: $TIMESTAMP"

#chmod +x /home/robin/backup-soultone.sh