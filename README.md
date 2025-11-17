# Stories Creator Server

Mini backend in plain PHP 8.2+ for storing and retrieving audio stories through a simple REST API.

## Project Structure

```
stories-creator-server/
├── config/
│   ├── config.example.php   # sample DB config
│   └── config.php           # actual config (edit this)
├── database/
│   └── schema.sql           # MySQL/MariaDB schema
├── public/
│   └── index.php            # front controller / router
├── scripts/
│   └── create_tables.php    # helper to run the schema via PDO
└── src/
    ├── Controllers/
    │   └── StoryController.php
    ├── Database/
    │   └── Connection.php
    ├── Models/
    │   └── Story.php
    ├── Repositories/
    │   └── StoryRepository.php
    ├── Router.php
    └── helpers.php
```

## API Overview

- `GET /api` → elenco degli endpoint disponibili
- `GET /api/stories?limit=50&offset=0`
- `GET /api/stories/{id}`
- `POST /api/stories`
- `PUT|PATCH /api/stories/{id}`
- `DELETE /api/stories/{id}`

All responses are JSON (`Content-Type: application/json`). Errors follow `{ "error": "message" }`.

## Requirements

- PHP 8.2+ with PDO MySQL extension enabled
- MySQL or MariaDB 10.x+

## Setup

1. **Install dependencies**
   - No Composer packages needed; ensure PHP has PDO MySQL.

2. **Configure the database**
   ```bash
   cp config/config.example.php config/config.php
   ```
   Edit `config/config.php` with your DB credentials or set the env vars `DB_HOST`, `DB_NAME`, etc.

3. **Create the database schema**
   ```bash
   php scripts/create_tables.php
   ```
   Alternatively run the SQL directly:
   ```bash
   mysql -u your_user -p stories_creator < database/schema.sql
   ```
   > Nota: all’avvio dell’API il server controlla automaticamente l’esistenza della tabella `stories` e, se mancante o con colonne obsolete, applica lo schema richiesto. Lo script rimane utile per eseguire l’inizializzazione manuale o in ambienti CI.

## Run the Development Server

```bash
php -S localhost:8000 -t public
```

The API will be available at `http://localhost:8000/api/stories`.