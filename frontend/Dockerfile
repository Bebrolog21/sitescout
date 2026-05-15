# syntax=docker/dockerfile:1.6
#
# SiteScout frontend — production image for Railway.
# Multi-stage: build static bundle with Vite, serve with Nginx.
#
# Note: VITE_* vars are inlined at build time. Pass them via Railway
# "Build-time variables" (Service → Variables → ...).
#

# ─── Stage 1: build ───────────────────────────────────────────────
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY . .

ARG VITE_API_BASE_URL
ARG VITE_DADATA_TOKEN
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
ENV VITE_DADATA_TOKEN=${VITE_DADATA_TOKEN}

RUN npm run build

# ─── Stage 2: serve ───────────────────────────────────────────────
FROM nginx:1.27-alpine

# Nginx config is a template — $PORT is substituted at container start.
COPY nginx.conf /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html

# The official nginx image already runs `envsubst` over /etc/nginx/templates/*.template
# into /etc/nginx/conf.d/ on startup, so $PORT from Railway gets baked into the conf.
EXPOSE 8080
