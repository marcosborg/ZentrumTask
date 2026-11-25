# Zentrum Ads

## Revisão local

O projeto usa Laravel 12, Blade, Bootstrap e Filament 4. O site público, o SLOT e o quadro de oportunidades partilham a aplicação existente.

Na raiz do repositório, execute `scripts\zentrum-ads-local.cmd`. O script usa o PHP do XAMPP, prepara uma base SQLite própria e inicia o Laravel em `http://127.0.0.1:8098`. Os emails da pré-visualização ficam no log. Para consultar a administração com os dados reais de acesso e de frota, use a opção de cópia abaixo.

Para rever com uma cópia atual dos dados, inicie o MySQL do XAMPP e execute `scripts\zentrum-ads-local.cmd --copiar-aws` com o perfil AWS da Zentrum disponível. Termine primeiro a pré-visualização anterior com Ctrl+C. O processo existente de replicação lê a origem Lightsail e importa para uma base local com nome próprio, indicado na consola. As tabelas transitórias de filas, sessões e cache são excluídas do conteúdo replicado. As migrações seguintes correm sobre a cópia local.

## Percurso de revisão

| Endereço | O que rever |
|---|---|
| `/` | Terceira área de negócio e novo slide dentro do carrossel |
| `/publicidade` | Formatos, configurador, resumo e formulário da campanha |
| `/publicidade/media-kit` | Página comercial e impressão em PDF pelo navegador |
| `/publicidade/privacidade` | Informação sobre os contactos e materiais recolhidos |
| `POST /publicidade/proposta` | Validação e registo do pedido |
| `/publicidade/obrigado` | Confirmação após submissão |
| `/slot#publicidade-slot` | Interesse em campanhas no Premium e adesão ao SLOT |
| `/admin/advertising-opportunities` | Pedido, responsável, estado, próximo contacto, notas, anexos e CSV |
| `/admin/advertising-formats` | Nome, descrição, imagem e disponibilidade dos formatos |
| `/blog` | Artigo sobre publicidade em TVDE em Portugal |

Os pedidos de carrinhas e do chat continuam a utilizar os quadros próprios através de `VanRentalKanbanService` e `ChatTaskCreationService`. O CMS permite editar o conteúdo do media kit. O artigo usa o modelo de publicações já existente. As imagens da campanha são simulações. A equipa confirma a região e a disponibilidade antes de apresentar preços.

## Dados e operação

- A primeira migração cria `advertising_formats`, o quadro «Zentrum Ads», nove estados comerciais e o artigo. Os pedidos usam `tasks`, os contactos posteriores usam `task_comments` e os ficheiros usam `task_attachments`. A origem UTM e as opções da campanha ficam nos metadados da oportunidade.
- A segunda migração cria `advertising_inventory`, `advertising_campaigns` e `advertising_reservations`. O inventário referencia as viaturas existentes. A chave única por viatura do inventário, espaço e dia permite reservar os espaços físicos sem dupla ocupação. O fluxo operacional de reservar, instalar e remover campanhas fica para a fase 2.
- A terceira migração identifica a página editorial do media kit no CMS com `cms_pages.content_key`.
- O simulador recolhe preferências. O orçamento é indicativo e fornecido pelo anunciante. A aplicação guarda o pedido sem calcular preços, alcance, remuneração de motoristas ou movimentos contabilísticos.
- Os anexos aceitam PDF, JPG e PNG até 5 MB e são descarregados através de uma rota autenticada. O disco local serve a pré-visualização. Em produção, o disco predefinido é S3 e pode configurar-se com `ADVERTISING_DISK`. O destino deve manter os objetos privados e permitir leitura e escrita à aplicação.
- A mensagem de confirmação usa a fila de correio da aplicação. O aviso comercial em produção reutiliza o serviço de notificações Android. Os eventos de analytics dependem do consentimento registado no site.

## Ficheiros principais

| Área | Ficheiros |
|---|---|
| Páginas | `resources/views/website/advertising*.blade.php`, `index.blade.php`, `slot.blade.php`, `layout.blade.php` |
| Componentes | `resources/views/components/{hero,navigation,tvde-offers,contact}.blade.php` |
| Imagens e interação | `public/website/assets/ads-*.webp`, `advertising.css`, `advertising.js`, `scripts.js` |
| Pedidos e autenticação | `routes/web.php`, `AdvertisingController`, `AdvertisingProposalRequest`, `AdvertisingAttribution`, `AdvertisingProposalReceived`, `WebsiteController`, `ContactTaskRequest`, `config/filesystems.php` |
| Administração | Resources `AdvertisingFormats` e `AdvertisingOpportunities`, vista `filament/advertising-history` e página `GenerateSitemap` |
| Modelos e migrações | `AdvertisingFormat`, factory e seeder, três migrações de 25 de novembro de 2026 |
| Verificação | `tests/Feature/AdvertisingProposalTest.php` e data de alocação fixa no cenário histórico de `TeslaManualMileageTest.php` |

## Publicação após aprovação

1. Concluir a revisão visual e funcional sobre a cópia da base de produção. Confirmar o email de confirmação, a receção pela equipa comercial, as permissões e o acesso aos anexos.
2. Confirmar cópia de segurança recuperável, disco privado persistente, limites PHP de upload compatíveis com 5 MB e serviço de filas operacional.
3. Publicar pelo fluxo existente de CI e deploy para Lightsail. O deploy executa as migrações. O conteúdo editorial e o quadro ficam disponíveis nessa passagem.
4. Regenerar o sitemap no painel e verificar a página pública, o SLOT e o media kit no domínio definitivo. A reversão do código preserva os dados comerciais recolhidos. Rever esses dados antes de qualquer reversão de esquema.
