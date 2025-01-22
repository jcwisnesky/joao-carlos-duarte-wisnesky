# Documentação da API

## Base URL
/api

## Endpoints

### 1. Login
/api/login
**Endpoint**: 

**Descrição**:  
Autentica um usuário e retorna um token de acesso para consumir outros endpoints.

**Headers**:
- `Content-Type: application/json`

**Body (JSON)**:

{
    "email": "admin@example.com",
    "password": "password"
}

### 2. Upload de Arquivo

**Endpoint**: 
/api/upload
**Descrição**:  
Permite o upload de um arquivo CSV para processamento e armazenamento.
**Headers**:
Authorization: Bearer {token}
Content-Type: multipart/form-data
**Body (Form-Data)**:
file: Arquivo CSV.

### 3. Histórico de Uploads
**Endpoint**: 
/api/history
**Descrição**:  
Retorna o histórico de arquivos enviados anteriormente.
**Headers**:
Authorization: Bearer {token}


### 3. Histórico de Uploads
**Endpoint**: 
/api/search
**Descrição**:  
Permite buscar dados em arquivos CSV previamente enviados, com base nos parâmetros de consulta.
**Headers**:
id(obrigatório): identificador no banco de dados do arquivo solicitado.
TckrSymb (opcional): Símbolo do ativo financeiro.
RptDt (opcional): Data de relatório no formato YYYY-MM-DD.

## Autenticação
Esta API utiliza autenticação baseada em token (Bearer Token). O token deve ser incluído no cabeçalho de todas as requisições protegidas.
