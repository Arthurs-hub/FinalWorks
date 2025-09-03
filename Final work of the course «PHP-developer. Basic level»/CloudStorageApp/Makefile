# Makefile для проекта CloudStorageApp

.PHONY: help install start lint clean

help:
	@echo "Доступные команды:"
	@echo "  install    - Установить PHP зависимости через Composer"
	@echo "  start      - Запустить встроенный веб-сервер PHP"
	@echo "  lint       - Проверить синтаксис всех PHP файлов в директории App"
	@echo "  clean      - Удалить директорию vendor и файл composer.lock"

install:
	@echo "Установка зависимостей Composer..."
	composer install

start:
	@echo "Запуск сервера для разработки на http://localhost:8000"
	php -S localhost:8000 -t public

lint:
	@echo "Проверка синтаксиса PHP файлов..."
	find App -name "*.php" -exec php -l {} \;

clean:
	@echo "Очистка проекта..."
	rm -rf vendor
	rm -f composer.lock