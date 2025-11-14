# FortStore - Backend

Aplicacao backend para loja virtual de cosmeticos do Fortnite desenvolvida em Laravel.

## Tecnologias Utilizadas

- PHP 8.2
- Laravel 11
- Laravel Sail (Docker)
- MySQL 8.0
- Laravel Breeze (Autenticacao)
- Laravel Sanctum (Tokens API)
- API Externa: https://dashboard.fortniteapi.io/

## Requisitos

- Docker e Docker Compose instalados
- Git

## Instalacao

1. Clone o repositorio:
```bash
git clone <url-do-repositorio>
cd fortstore-srv
```

2. Copie o arquivo de ambiente:
```bash
cp .env.example .env
```

3. Configure a chave da API Fortnite no arquivo `.env`:
   - Acesse https://dashboard.fortniteapi.io/register
   - Crie uma conta gratuita
   - Apos o cadastro, voce recebera uma chave de API
   - Adicione a chave no arquivo `.env`:
```
FORTNITE_API_KEY=sua-chave-aqui
```

4. Instale as dependencias:
```bash
composer install
```

5. Execute o Laravel Sail para subir os containers:
```bash
./vendor/bin/sail up -d
```

6. Gere a chave da aplicacao:
```bash
./vendor/bin/sail artisan key:generate
```

7. Execute as migracoes:
```bash
./vendor/bin/sail artisan migrate
```

8. Para popular o banco de dados ou atualizar
```bash
./vendor/bin/sail artisan app:import-fortnite-cosmetics
```

## Como Rodar

Iniciar:
```bash
./vendor/bin/sail up -d
```

Parar:
```bash
./vendor/bin/sail down
```

O backend estara disponivel em: http://localhost:8000

## Endpoints Principais

- `GET /api/cosmetics` - Lista todos os cosmeticos (com filtros)
- `GET /api/cosmetics/new` - Lista cosmeticos novos
- `GET /api/shop` - Lista cosmeticos a venda
- `GET /api/cosmetics/{id}` - Detalhes de um cosmetico
- `POST /api/auth/register` - Cadastro de usuario
- `POST /api/auth/login` - Login
- `POST /api/cosmetics/{id}/purchase` - Comprar cosmetico
- `POST /api/cosmetics/{id}/return` - Devolver cosmetico

## Decisoes Tecnicas

- MySQL 8.0 via Laravel Sail para ambiente padronizado
- Laravel Sail escolhido por ser a solucao oficial do Laravel para Docker
- Laravel Breeze para sistema de autenticacao
- Laravel Sanctum para autenticacao via tokens API
- API FortniteApi.io para obter dados dos cosmeticos
- Banco de dados ja populado com os cosmeticos disponiveis
- Filtros implementados no backend para melhor performance
