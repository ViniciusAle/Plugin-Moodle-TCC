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
$string['dashboardhelp'] = 'Após importar os arquivos, acesse a página de Insights para ver os gráficos de engajamento e a análise cruzada.';
$string['nogoogleconfig'] = 'O seletor do Google Drive ainda não foi configurado. Peça a um administrador do site para preencher o Client ID e a chave da API do Picker em Administração do site > Extensões > Plugins locais > Rastreador de Learning Analytics.';

// Analytics.
$string['noinsights'] = 'Nenhum insight está registrado.';
$string['noinsightdata'] = 'Ainda não há dados suficientes coletados para montar este gráfico.';
$string['insight_engagement_title'] = 'Engajamento temporal';
$string['insight_engagement_desc'] = 'Tempo total de uso da plataforma por aluno.';
$string['insight_behavior_title'] = 'Comportamento em tarefas';
$string['insight_behavior_desc'] = 'Tempo médio até a entrega comparado à incidência de Ctrl+C / Ctrl+V, por atividade.';
$string['insight_csvcross_title'] = 'Análise cruzada dos CSVs importados';
$string['insight_csvcross_desc'] = 'Linhas importadas e média da primeira coluna numérica, por arquivo CSV importado.';
$string['minutesonplatform'] = 'Minutos na plataforma';
$string['avgminutestosubmit'] = 'Média de minutos até a entrega';
$string['copypasteevents'] = 'Eventos de copiar/colar';
$string['importedrows'] = 'Linhas importadas';
$string['avgnumericvalue'] = 'Valor numérico médio';

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
