.PHONY: setup up down migrate test lint

setup:
	cd travess-api && composer install
	pnpm install

up:
	docker compose up -d

down:
	docker compose down

migrate:
	cd travess-api && php artisan migrate --seed

test:
	cd travess-api && php artisan test
	pnpm test

lint:
	pnpm lint
