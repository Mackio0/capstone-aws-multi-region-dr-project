#!/bin/bash
# 1. Update and Install Stack
dnf update -y
dnf install -y nginx php-fpm php-mysqli php-json git

# 2. Get Metadata for the UI
TOKEN=$(curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600")
INSTANCE_ID=$(curl -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/instance-id)
AZ=$(curl -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/placement/availability-zone)

# !!IMPORTANT FIX: Nginx server config fix
sed -i 's/listen       80 default_server;/listen       80;/g' /etc/nginx/nginx.conf

# 3. Clone the Public Repo
rm -rf /var/www/html/*
git clone https://github.com/Mackio0/capstone-aws-multi-region-dr-project.git /tmp/app
cp -r /tmp/app/* /var/www/html/

# 4. SECURE CONFIG: Create Nginx config with injected Secrets
# REPLACE THESE WITH YOUR ACTUAL RDS ENDPOINT AND PASSWORD
DB_ENDPOINT="capstone-primary-db.xxxxxxxxxxxxx.rds.amazonaws.com"
DB_PASSWORD="yourpassword"

cat <<EOF > /etc/nginx/conf.d/php_app.conf
server {
    listen 80;
    server_name _;
    root /var/www/html;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        
        # RDS Secrets
        fastcgi_param DB_HOST "$DB_ENDPOINT";
        fastcgi_param DB_USER "admin";
        fastcgi_param DB_PASS "$DB_PASSWORD";

        # Cloud Metadata (Passed from Bash to PHP)
        fastcgi_param AWS_INSTANCE_ID "$INSTANCE_ID";
        fastcgi_param AWS_AZ "$AZ";
        fastcgi_param AWS_REGION "ap-southeast-2";
        
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF

# 5. Fix Permissions & Start Services
chown -R nginx:nginx /var/www/html
chmod -R 755 /var/www/html

systemctl enable --now php-fpm nginx
systemctl restart nginx