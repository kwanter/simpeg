# Stage 1: Build assets with the Node version required by Vite 8.
FROM node:22-bookworm-slim AS builder

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY . /var/www
RUN npm run build

# Stage 2: Nginx production image
FROM nginx:alpine

# Copy custom Nginx configuration
# -----------------------------------------------------------
# Replace the default Nginx configuration with our custom one
# that is optimized for serving a Laravel application.
# -----------------------------------------------------------
COPY ./docker/production/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public assets from the builder stage.
COPY --from=builder /var/www/public /var/www/public

# Expose public profile assets from the mounted storage volume.
# Nginx separately denies /storage/dokumen/, which remains policy-gated in Laravel.
RUN ln -s /var/www/storage/app/public /var/www/public/storage

# Set the working directory to the public folder
WORKDIR /var/www/public

# Expose port 80 and start Nginx
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
