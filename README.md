# VitaGesta — rede de apoio nutricional na gestação

Protótipo funcional de uma plataforma que liga gestantes a nutricionistas,
em **HTML, CSS e JavaScript puro + PHP 8 e MySQL**, sem framework e sem build.
A gestante calcula o IMC gestacional, vê se o ganho de peso está dentro da faixa
recomendada para a semana e, se não estiver, é encaminhada com prioridade para
nutricionistas perto dela.

**No ar: [vitagesta.matheusmorais.com](https://vitagesta.matheusmorais.com)**
— painel ao vivo em [/#ao-vivo](https://vitagesta.matheusmorais.com/#ao-vivo).

> Feito para o trabalho de Economia e Marketing do grupo Materno Infantil
> (Facunicamps). A ideia, o roteiro e a identidade visual são do grupo; o
> desenvolvimento é meu. Nomes, distâncias, avaliações e preços são fictícios.

## O que tem

**Site público**
- IMC gestacional pela recomendação do **Institute of Medicine (2009)**:
  faixa de ganho por IMC pré-gestacional, semana a semana
- Resultado com os três cenários do roteiro do grupo: adequado leva a
  orientações nutricionais; abaixo ou acima leva a encaminhamento prioritário
- Curva de ganho de peso com os trimestres marcados, no estilo da caderneta da gestante
- Busca de nutricionistas ordenada **só por distância e avaliação**, nunca por plano pago
- **Painel ao vivo** para o telão da apresentação: o público faz o cálculo pelo
  QR Code e os números (anônimos) aparecem na hora

**Área da gestante** (conta criada a partir do resultado)
- Registro de pesagens e curva com o histórico
- Plano alimentar enviado pela nutricionista
- Pedido e acompanhamento de consultas
- Relatório para levar ao pré-natal, pronto para imprimir ou salvar em PDF

**Painel da nutricionista**
- Visão geral com pacientes que precisam de atenção, próximas consultas e pedidos
- Ficha da paciente com histórico de peso e montador de plano alimentar por refeição
- Agenda para confirmar (com data e hora) ou recusar pedidos de consulta
- Gestantes que chegam pelo site aparecem para todas e podem ser assumidas

Também dá para **instalar como app** (PWA) no Android e no computador.

## Segurança

| Ponto | Como está |
|---|---|
| SQL injection | Todo SQL com prepared statements (PDO) |
| Senhas | `password_hash`, sessão renovada ao entrar, atraso de 0,5 s a cada senha errada |
| Sessão | Cookie `httponly` e `SameSite=Lax`, o que barra POST de outro site com a sessão |
| Dados das pacientes | Cada nutricionista só vê as próprias pacientes, as novas do site e quem pediu consulta com ela; o resto dá 404 |
| Criar conta | Só quem fez o cálculo pode transformá-lo em conta: o servidor devolve um token secreto que é exigido na criação |
| XSS | Tudo o que vem do usuário é escapado antes de ir para o HTML |
| Painel ao vivo | Só números agregados, sem nome nem e-mail |
| Configuração | Credenciais fora do Git, em `api/config.php` (bloqueado pelo `.htaccess`) |

## Estrutura

```
index.html            SPA com rotas por hash: site público, login e área logada (shell com barra lateral)
api/                  PHP: login, conta, pacientes, planos, gestante, consultas, stats (painel ao vivo), reset
schema.sql            tabelas e clientes de exemplo
manifest.webmanifest  app instalável; sw.js usa a rede primeiro e só cai no cache sem internet
material-apresentacao/  planilha de custos e receita, QR Code e exemplos para os slides
```

## Rodando localmente

Requisitos: PHP 8.1+ e MySQL/MariaDB (o XAMPP serve).

1. Crie o banco e rode `schema.sql`.
2. Copie `api/config.example.php` para `api/config.php` e preencha as credenciais e um `reset_token`.
3. Crie as contas de nutricionista com `password_hash` (a tabela `nutricionistas` não aceita senha em texto puro).
4. Sirva a pasta pelo Apache ou com `php -S localhost:8000` e abra no navegador.
5. `api/reset.php?t=<reset_token>` recria os dados de demonstração.

Abrindo o `index.html` direto, sem PHP, o site público e a calculadora funcionam; login e área logada precisam do servidor.

## Créditos

- Projeto Materno Infantil, disciplina de Economia e Marketing, Facunicamps Centro Universitário.
  Profª Patrícia Cristina Barreto Lobo. Discentes: Ábida Acsa, Leticia Rodrigues, Maryanna de Morais e Thaís Rodrigues.
- Referências: Institute of Medicine (2009); Lobo et al. (2019); Sousa et al. (2013).
- Fotos do [Unsplash](https://unsplash.com), por Jeferson Santu, Jonathan Caliguire e K15 Photos.
