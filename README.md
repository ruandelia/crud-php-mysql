# CRUD de Usuários - PHP, JSON e MySQL 🚀

Este é um projeto didático desenvolvido para praticar as operações fundamentais de um sistema (CRUD) utilizando PHP. O diferencial deste projeto é o armazenamento híbrido, onde os dados são geridos num ficheiro JSON e sincronizados com um banco de dados MySQL.

## 🛠️ Tecnologias Utilizadas
- **Frontend:** HTML5, CSS3, JavaScript (Fetch API)
- **Backend:** PHP (PDO para conexão segura)
- **Armazenamento:** JSON (persistência em ficheiro) e MySQL (base de dados relacional)

## 📋 Funcionalidades
- [x] Inserir novo usuário (gera ID automático no JSON e guarda no Banco)
- [x] Listar usuários existentes
- [x] Atualizar dados de um usuário
- [x] Eliminar registros
- [x] Sincronização manual entre o ficheiro JSON e o MySQL

## 🔧 Como Configurar o Projeto

### 1. Requisitos
- Servidor local (XAMPP, WAMP ou Laragon)
- Git instalado

### 2. Base de Dados
Cria uma base de dados chamada `agenda_api` e executa o seguinte comando SQL para criar a tabela:

```sql
CREATE TABLE tbUSUARIO (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
);
