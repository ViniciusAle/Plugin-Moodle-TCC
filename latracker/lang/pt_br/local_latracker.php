<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Strings em Português (Brasil) para local_latracker.
 *
 * @package     local_latracker
 * @copyright   2026 Learning Analytics Tracker
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Rastreador de Learning Analytics';
$string['insights'] = 'Insights de Learning Analytics';
$string['dashboardtitle'] = 'Painel do LA Tracker: {$a}';
$string['analyticstitle'] = 'Learning Analytics: {$a}';

// Capacidades.
$string['latracker:view'] = 'Visualizar o painel do LA Tracker';
$string['latracker:viewanalytics'] = 'Visualizar a página de Insights de Learning Analytics';
$string['latracker:manageintegrations'] = 'Gerenciar a integração com o Google Drive e importar arquivos CSV';
$string['latracker:track'] = 'Ter eventos de navegação e de atividades rastreados';

// Painel.
$string['googledrivepanel'] = 'Integração com o Google Drive';
$string['selectcsvexplain'] = 'Selecione os arquivos CSV que deseja importar e processar nesta disciplina. Uma janela do Google será aberta para você entrar na conta e escolher os arquivos.';
$string['selectfromdrive'] = 'Selecionar arquivos CSV do Google Drive';
$string['opening'] = 'Abrindo o Google Drive...';
$string['nofileselected'] = 'Nenhum arquivo foi selecionado.';
$string['importing'] = 'Baixando e importando os arquivos selecionados...';
$string['importsuccess'] = '{$a} arquivo(s) importado(s) com sucesso.';
$string['shortcuts'] = 'Atalhos';
$string['gotoinsights'] = 'Ir para Insights de Learning Analytics';
$string['dashboardhelp'] = 'Após importar os arquivos, acesse a página de Insights para ver o painel de learning analytics.';
$string['nogoogleconfig'] = 'O seletor do Google Drive ainda não foi configurado. Peça a um administrador do site para preencher o Client ID e a chave da API do Picker em Administração do site > Extensões > Plugins locais > Rastreador de Learning Analytics.';

// Analytics.
$string['noinsights'] = 'Nenhum insight está registrado.';
$string['noinsightdata'] = 'Ainda não há dados suficientes coletados para montar este gráfico.';
$string['kpi_students'] = 'Alunos monitorados';
$string['kpi_avgminutes'] = 'Média de minutos por aluno';
$string['kpi_activities'] = 'Atividades com dados de conclusão';
$string['kpi_late'] = 'Conclusões atrasadas';
$string['section_engagement'] = 'Engajamento ao longo do tempo';
$string['section_completion'] = 'Conclusão e prazos';
$string['section_questions'] = 'Interações em respostas';
$string['section_trail'] = 'Trilha de navegação do aluno';
$string['section_behaviour'] = 'Indicadores de comportamento';
$string['aboutindicator'] = 'Sobre este indicador';
$string['insight_engagementtimeline_title'] = 'Engajamento ao longo do tempo';
$string['insight_engagementtimeline_desc'] = 'Minutos acumulados na plataforma por aluno, dia a dia. Use o filtro para escolher quais alunos aparecem.';
$string['selectstudents'] = 'Filtrar alunos';
$string['selectall'] = 'Selecionar todos';
$string['clearselection'] = 'Limpar seleção';
$string['insight_behavior_title'] = 'Comportamento em tarefas';
$string['insight_behavior_desc'] = 'Tempo médio até a entrega comparado à incidência de Ctrl+C / Ctrl+V, por atividade.';
$string['insight_csvcross_title'] = 'Análise cruzada dos CSVs importados';
$string['insight_csvcross_desc'] = 'Linhas importadas e média da primeira coluna numérica, por arquivo CSV importado.';
$string['insight_clickengagement_title'] = 'Engajamento por cliques';
$string['insight_clickengagement_desc'] = 'Total de cliques do mouse por aluno - um indício de interação ativa, diferente de só ter a aba aberta.';
$string['insight_rageclick_title'] = 'Rajadas de clique (indício de dificuldade)';
$string['insight_rageclick_desc'] = 'Sequências de vários cliques em rápida sucessão e próximos entre si na tela, por atividade - um sinal comum de confusão ou de algo que não está respondendo.';
$string['insight_submissiontiming_title'] = 'Tempo de entrega das atividades';
$string['insight_submissiontiming_desc'] = 'Quanto tempo antes do prazo os alunos realmente concluíram a atividade (usando o "Marcar como concluído" do Moodle), comparado à "Data esperada de conclusão" configurada em cada atividade. Funciona para qualquer tipo de atividade com rastreamento de conclusão ativado, incluindo ferramentas externas (LTI).';
$string['insight_optionclicks_title'] = 'Padrão de respostas em questões';
$string['insight_optionclicks_desc'] = 'Em qual alternativa os alunos clicaram numa Escolha ou Questionário. Só questões de múltipla escolha/verdadeiro-falso e atividades de Escolha geram dados aqui.';
$string['nooptionclickactivities'] = 'Esta disciplina ainda não tem atividades de Questionário ou Escolha.';
$string['nooptionclickdata'] = 'Ainda não há cliques em alternativas registrados para esta atividade.';
$string['selectactivity'] = 'Atividade';
$string['selectactivities'] = 'Filtrar atividades';
$string['loadingoptions'] = 'Carregando...';
$string['viewsequence'] = 'Ver sequência de cliques de um aluno';
$string['finalclick'] = 'resposta final';
$string['nosequencedata'] = 'Este aluno não tem cliques registrados nesta atividade.';
$string['insight_studentlogs_title'] = 'Trilha de navegação do aluno';
$string['insight_studentlogs_desc'] = 'A atividade de um aluno, lida diretamente dos logs nativos do Moodle e organizada em tabela - os mesmos dados de Administração do site > Relatórios > Logs, filtrados para um único aluno.';
$string['nocourseenrolledusers'] = 'Esta disciplina não tem usuários inscritos.';
$string['selectstudentlog'] = 'Aluno';
$string['downloadcsv'] = 'Baixar CSV deste aluno';
$string['downloadallcsv'] = 'Baixar CSV de todos os alunos';
$string['loadinglogs'] = 'Carregando trilha de navegação...';
$string['loadingalllogs'] = 'Preparando o CSV de todos os alunos inscritos...';
$string['nologdata'] = 'Nenhum registro de log foi encontrado para este aluno nesta disciplina.';
$string['noalllogdata'] = 'Nenhum registro de log foi encontrado para os alunos inscritos nesta disciplina.';
$string['logcolumn_time'] = 'Hora';
$string['logcolumn_context'] = 'Contexto';
$string['logcolumn_component'] = 'Componente';
$string['logcolumn_event'] = 'Evento';
$string['logcolumn_student'] = 'Aluno';
$string['clickeventwithquestion'] = 'Selecionou "{$a->option}" na questão {$a->number}';
$string['clickeventwithoutquestion'] = 'Selecionou "{$a}"';
$string['insight_essaybehavior_title'] = 'Comportamento de escrita em discursivas';
$string['insight_essaybehavior_desc'] = 'Como os alunos escreveram as respostas das questões discursivas: tempo com o campo em foco, tempo de digitação ativa, pausas, palavras e texto colado. O conteúdo digitado nunca é armazenado, apenas contagens e tamanhos. A proporção de texto colado é uma estimativa (caracteres colados em relação ao tamanho final da resposta) e serve como sinal para investigar, não como prova de cópia.';
$string['noessayactivities'] = 'Esta disciplina ainda não tem atividades de Questionário.';
$string['essayquestion'] = 'Questão';
$string['loadingessay'] = 'Carregando...';
$string['noessaydata'] = 'Ainda não há registros de escrita em questões discursivas para esta atividade.';
$string['noessayevolution'] = 'Não há evolução da escrita registrada para este aluno nesta questão.';
$string['essaycompositiontitle'] = 'Texto digitado x colado (resposta final)';
$string['essaythresholdnote'] = 'Cores de "% colado": verde abaixo de 20%, amarelo de 20% a 49% e vermelho a partir de 50% (limites indicativos).';
$string['essayviewevolution'] = 'Ver a evolução da escrita de um aluno';
$string['essaycol_student'] = 'Aluno';
$string['essaycol_focus'] = 'Tempo em foco';
$string['essaycol_typing'] = 'Tempo digitando';
$string['essaycol_pauses'] = 'Pausas';
$string['essaycol_words'] = 'Palavras';
$string['essaycol_pastes'] = 'Colagens';
$string['essaycol_pastedchars'] = 'Caracteres colados';
$string['essaycol_pastedpercent'] = '% colado';
$string['essaytypedchars'] = 'Digitado (estimado)';
$string['essaypastedchars'] = 'Colado';
$string['essaycharsseries'] = 'Caracteres';
$string['essaywordsseries'] = 'Palavras';
$string['essaypasteseries'] = 'Colagem';
$string['essaytypingevent'] = 'Digitou por {$a->typing} na questão {$a->number} ({$a->words} palavras, {$a->pauses} pausas)';
$string['essaytypingeventpasted'] = 'Digitou por {$a->typing} na questão {$a->number} ({$a->words} palavras, {$a->pauses} pausas, {$a->percent}% colado)';
$string['essaypasteevent'] = 'Colou {$a->chars} caracteres na questão {$a->number}';
$string['avgminutestosubmit'] ='Média de minutos até a entrega';
$string['copypasteevents'] = 'Eventos de copiar/colar';
$string['importedrows'] = 'Linhas importadas';
$string['avgnumericvalue'] = 'Valor numérico médio';
$string['clickcount'] = 'Cliques';
$string['rageburstcount'] = 'Rajadas de clique';
$string['submissionbucket_early3'] = 'Mais de 3 dias antes';
$string['submissionbucket_early1to3'] = '1-3 dias antes';
$string['submissionbucket_early6to24h'] = '6-24 horas antes';
$string['submissionbucket_lastminute'] = 'Últimas horas';
$string['submissionbucket_late'] = 'Atrasado';

// Configurações.
$string['pickerheading'] = 'Seletor do Google Drive';
$string['pickerheading_desc'] = 'O seletor de CSV roda inteiramente no navegador (Google Identity Services + o widget Google Picker), então o servidor do Moodle não precisa de acesso de saída ao Google. Veja o README.md para o passo a passo exato no Google Cloud Console.';
$string['googleclientid'] = 'Client ID OAuth 2.0 do Google';
$string['googleclientid_desc'] = 'Um Client ID OAuth 2.0 do tipo "Aplicativo da Web" do Google Cloud Console, com este site cadastrado em "Origens JavaScript autorizadas". Este valor é público e seguro para expor no navegador.';
$string['pickerapikey'] = 'Chave de API do Google Picker';
$string['pickerapikey_desc'] = 'Uma chave de API do Google Cloud Console, restrita à Picker API e ao referenciador HTTP (referrer) deste site.';
$string['trackingenabled'] = 'Habilitar rastreamento de estudantes';
$string['trackingenabled_desc'] = 'Quando desabilitado, o módulo de rastreamento não é injetado em nenhuma página e nenhum evento é registrado.';

// Privacidade.
$string['privacy:metadata:local_latracker_pageview'] = 'Tempo que um usuário permaneceu em uma página.';
$string['privacy:metadata:local_latracker_pageview:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_pageview:courseid'] = 'A disciplina onde a visualização ocorreu.';
$string['privacy:metadata:local_latracker_pageview:pageurl'] = 'A URL da página visitada.';
$string['privacy:metadata:local_latracker_pageview:duration'] = 'Quantos segundos o usuário permaneceu na página.';
$string['privacy:metadata:local_latracker_pageview:timecreated'] = 'Quando a visualização foi registrada.';
$string['privacy:metadata:local_latracker_sessiontime'] = 'Tempo acumulado de uso da plataforma por um usuário em uma disciplina.';
$string['privacy:metadata:local_latracker_sessiontime:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_sessiontime:courseid'] = 'A disciplina.';
$string['privacy:metadata:local_latracker_sessiontime:totaltime'] = 'Total de segundos acumulados.';
$string['privacy:metadata:local_latracker_activitytime'] = 'Tempo que um usuário levou para resolver uma atividade, da abertura até a entrega.';
$string['privacy:metadata:local_latracker_activitytime:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_activitytime:cmid'] = 'O id da atividade (course module).';
$string['privacy:metadata:local_latracker_activitytime:timestarted'] = 'Quando a atividade foi aberta.';
$string['privacy:metadata:local_latracker_activitytime:timesubmitted'] = 'Quando a atividade foi entregue.';
$string['privacy:metadata:local_latracker_event'] = 'Uso de atalhos de copiar/colar registrado durante a navegação ou resolução de uma atividade.';
$string['privacy:metadata:local_latracker_event:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_event:eventtype'] = 'Se o evento foi uma cópia ou uma colagem.';
$string['privacy:metadata:local_latracker_event:pageurl'] = 'A URL da página onde o evento ocorreu.';
$string['privacy:metadata:local_latracker_click'] = 'Amostras de cliques do mouse, usadas nos insights de engajamento por cliques e rajadas de clique.';
$string['privacy:metadata:local_latracker_click:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_click:cmid'] = 'O id da atividade (course module), se o clique ocorreu numa página de atividade.';
$string['privacy:metadata:local_latracker_click:pageurl'] = 'A URL da página onde o clique ocorreu.';
$string['privacy:metadata:local_latracker_click:targettag'] = 'O nome da tag HTML do elemento clicado.';
$string['privacy:metadata:local_latracker_click:timecreatedms'] = 'Quando o clique ocorreu, em milissegundos.';
$string['privacy:metadata:local_latracker_click:optionlabel'] = 'Texto da alternativa selecionada, se o clique correspondeu a uma.';
$string['privacy:metadata:local_latracker_click:questioncontext'] = 'Trecho que identifica a qual questão a alternativa pertence.';
$string['privacy:metadata:local_latracker_submission'] = 'Horário real de entrega de uma atividade comparado ao seu prazo.';
$string['privacy:metadata:local_latracker_submission:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_submission:cmid'] = 'O id da atividade (course module).';
$string['privacy:metadata:local_latracker_submission:duedate'] = 'O prazo de entrega da atividade.';
$string['privacy:metadata:local_latracker_submission:timesubmitted'] = 'Quando o usuário realmente entregou.';
$string['privacy:metadata:local_latracker_submission:secondsbeforedue'] = 'Quantos segundos antes do prazo a entrega ocorreu (negativo se atrasada).';
$string['privacy:metadata:local_latracker_essay'] = 'Comportamento de escrita em questões discursivas: tempo em foco e de digitação, pausas, colagens e tamanho da resposta. O texto escrito nunca é armazenado.';
$string['privacy:metadata:local_latracker_essay:userid'] = 'O id do usuário.';
$string['privacy:metadata:local_latracker_essay:cmid'] = 'O id do questionário (módulo do curso).';
$string['privacy:metadata:local_latracker_essay:questioncontext'] = 'Trecho curto que identifica a questão discursiva.';
$string['privacy:metadata:local_latracker_essay:focusseconds'] = 'Segundos com o campo da resposta em foco.';
$string['privacy:metadata:local_latracker_essay:typingseconds'] = 'Segundos de digitação ativa.';
$string['privacy:metadata:local_latracker_essay:keystrokes'] = 'Quantidade de eventos de digitação/exclusão (não o conteúdo).';
$string['privacy:metadata:local_latracker_essay:pausecount'] = 'Quantidade de pausas durante a digitação.';
$string['privacy:metadata:local_latracker_essay:pastecount'] = 'Quantidade de eventos de colar.';
$string['privacy:metadata:local_latracker_essay:pastedchars'] = 'Total de caracteres colados (não o texto colado).';
$string['privacy:metadata:local_latracker_essay:finalchars'] = 'Tamanho da resposta em caracteres.';
$string['privacy:metadata:local_latracker_essay:finalwords'] = 'Tamanho da resposta em palavras.';
$string['privacy:metadata:local_latracker_essay:timeline'] = 'Tamanho da resposta (caracteres e palavras) amostrado ao longo do tempo.';
$string['privacy:metadata:local_latracker_essay:pastelog'] = 'Quando cada colagem ocorreu e quantos caracteres tinha.';
