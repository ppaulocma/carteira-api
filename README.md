# Carteira API

API REST de carteira financeira digital. Permite cadastro de usuários, autenticação e operações de depósito, transferência e estorno de transações.

## Tecnologias

- PHP 8.3 / Laravel 13
- Laravel Sanctum — autenticação stateless por Bearer Token
- MySQL 8.4
- Arquitetura em camadas: Controller → Service → Repository

## Endpoints

### Públicos

| Método | Rota | Body |
|--------|------|------|
| POST | `/api/register` | `name`, `email`, `password` |
| POST | `/api/login` | `email`, `password` → retorna `token` |

### Autenticados `Authorization: Bearer {token}`

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/user` | Dados do usuário logado com saldo |
| GET | `/api/transactions` | Histórico de transações |
| GET | `/api/users/find?email=` | Busca usuário por e-mail |
| POST | `/api/deposit` | Depositar — body: `amount` |
| POST | `/api/transfer` | Transferir — body: `receiver_id`, `amount` |
| POST | `/api/transactions/{id}/reverse` | Estornar uma transação |

## Regras de negócio

- Saldo não pode ser negativo no momento da transferência
- Depósito sempre soma ao saldo atual, mesmo que esteja negativo
- Só o dono da transação pode estorná-la
- Transações do tipo `reverse` ou já estornadas não podem ser estornadas novamente
- Todas as operações de saldo rodam dentro de `DB::transaction`

## Como rodar

### Com Docker

```bash
cp .env.example .env
# Edite o .env: DB_HOST=mysql, DB_USERNAME=sail, DB_PASSWORD=password, DB_DATABASE=laravel

docker compose up -d
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
```

API disponível em `http://localhost`.

### Sem Docker

Requisitos: PHP 8.3+, Composer, MySQL ou SQLite.

```bash
cp .env.example .env
# Configure o banco no .env (SQLite por padrão no .env.example)

composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

API disponível em `http://localhost:8000`.

## Testes

```bash
php artisan test
```
