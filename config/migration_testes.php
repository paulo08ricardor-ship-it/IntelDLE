<?php
/**
 * Script de Migração: Criação e Povoamento da Tabela `componentes_testes`
 * Central de Diagnóstico e Testes - Inteldle
 * 
 * Executável via PHP CLI ou inclusão direta.
 */

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/database.php';

try {
    $db = (new Database())->conectar();
    
    // Início da transação para garantir atomicidade
    $db->beginTransaction();

    // 1. Verificar se a tabela já existe e se possui a constraint correta das 5 categorias
    $tableSql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='componentes_testes'")->fetchColumn();

    $precisaRecriar = true;
    if (!empty($tableSql)) {
        // Verifica se a tabela já suporta as novas categorias no CHECK constraint
        if (str_contains($tableSql, "'rede'") && str_contains($tableSql, "'seguranca'") && str_contains($tableSql, "'geral'")) {
            $colunas = $db->query("PRAGMA table_info(componentes_testes)")->fetchAll(PDO::FETCH_ASSOC);
            $nomesColunas = array_column($colunas, 'name');
            if (in_array('componente_id', $nomesColunas) && in_array('resultado_normal', $nomesColunas) && in_array('resultado_anomalia', $nomesColunas)) {
                $precisaRecriar = false;
            }
        }
    }

    if ($precisaRecriar) {
        $db->exec("DROP TABLE IF EXISTS componentes_testes;");
        $db->exec("
            CREATE TABLE componentes_testes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                componente_id INTEGER,
                nome TEXT NOT NULL,
                tipo TEXT NOT NULL CHECK(tipo IN ('hardware', 'software', 'rede', 'seguranca', 'geral')),
                descricao TEXT NOT NULL,
                icone TEXT NOT NULL,
                comando_ou_acao TEXT,
                resultado_esperado TEXT,
                tempo_estimado TEXT,
                resultado_normal TEXT NOT NULL,
                resultado_anomalia TEXT NOT NULL,
                FOREIGN KEY (componente_id) REFERENCES componentes(id) ON DELETE SET NULL
            );
        ");
    }

    // 2. Mapeamento de componentes existentes
    $stmtComps = $db->query("SELECT id, nome FROM componentes");
    $mapaCompId = [];
    while ($row = $stmtComps->fetch(PDO::FETCH_ASSOC)) {
        $mapaCompId[$row['nome']] = (int)$row['id'];
    }

    // 3. Testes de Hardware e Software para diagnóstico realista
    $testesSeed = [
        // --- TESTES DE HARDWARE ---
        [
            'comp' => 'Fonte',
            'nome' => 'Teste de Tensão da Fonte (PSU)',
            'tipo' => 'hardware',
            'descricao' => 'Mede as linhas de tensão (+12V, +5V, +3.3V) e sinal Power Good (PG) da fonte de alimentação com multímetro digital e testador ATX.',
            'icone' => 'fonte4.png',
            'comando_ou_acao' => 'Medição de pinagem ATX 24 pinos com multímetro digital em escala contínua (DCV) e carga resistiva',
            'resultado_esperado' => 'Tensões dentro da tolerância de ±5% (+12V: 11.4V-12.6V, +5V: 4.75V-5.25V, +3.3V: 3.14V-3.47V) e sinal PG ativo.',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Tensões nominais perfeitamente estáveis (+12.08V, +5.02V, +3.31V). Sinal Power Good (PG) ativo em 310ms. Eficiência energética dentro do padrão ATX sem ruído elétrico.',
            'resultado_anomalia' => 'FALHA CRÍTICA DE ALIMENTAÇÃO: Linha de +12V registrando 0.00V (ou queda severa para 8.2V). Sinal Power Good inativo (0ms). Curto-circuito interno ou capacitor do primário/secundário rompido na PSU.'
        ],
        [
            'comp' => 'RAM',
            'nome' => 'Diagnóstico de Memória RAM (MemTest)',
            'tipo' => 'hardware',
            'descricao' => 'Executa testes de estresse, inversão de bits (Moving Inversions) e padrões pseudoaleatórios em cada módulo para detectar setores defeituosos de memória.',
            'icone' => 'ram.png',
            'comando_ou_acao' => 'Inicialização via MemTest86 / Windows Memory Diagnostic em loop de testes de padrão',
            'resultado_esperado' => '0 erros encontrados em todos os passes de leitura e escrita nos endereços 0x0000-0xFFFF.',
            'tempo_estimado' => '15 minutos',
            'resultado_normal' => '0 erros detectados em 4 passes de leitura/escrita. Latências CL e frequência nominal sincronizadas em Dual Channel. Barramento de dados 100% íntegro.',
            'resultado_anomalia' => 'ERRO DE INTEGRIDADE DE MEMÓRIA: Múltiplas falhas de leitura/escrita no endereço 0x0004FA20. Inversão de bits detectada no módulo 1, causando corrupção de memória e instabilidade severa.'
        ],
        [
            'comp' => 'CPU',
            'nome' => 'Teste de Estresse e Instruções CPU',
            'tipo' => 'hardware',
            'descricao' => 'Submete as unidades lógicas e aritméticas (ALU/FPU) da CPU a cálculo de ponto flutuante intensivo para testar a integridade física dos núcleos.',
            'icone' => 'cpu.png',
            'comando_ou_acao' => 'Execução do Prime95 Small FFTs / Intel Processor Diagnostic Tool (IPDT)',
            'resultado_esperado' => 'Cálculos de ponto flutuante concluídos com sucesso sem erros de paridade, interrupção de threads ou thermal throttling.',
            'tempo_estimado' => '10 minutos',
            'resultado_normal' => 'Todos os núcleos e threads executaram instruções complexas a 100% sem erros de paridade. VCore estável e frequência Boost atingida normalmente.',
            'resultado_anomalia' => 'FALHA DE INSTRUÇÃO NA CPU: Erro fatal de paridade aritmética ou thread interrompida subitamente sob carga. Falha em microcódigo ou dano físico nos registradores internos.'
        ],
        [
            'comp' => 'Cooler',
            'nome' => 'Monitoramento Térmico e Rotação do Cooler',
            'tipo' => 'hardware',
            'descricao' => 'Mede a temperatura do die da CPU, eficiência da condutividade térmica e rotação (RPM) da ventoinha do dissipador sob estresse.',
            'icone' => 'cooler.png',
            'comando_ou_acao' => 'Leitura dos sensores de temperatura via HWMonitor com monitoramento de tacômetro PWM',
            'resultado_esperado' => 'Temperatura máxima estabilizada abaixo de 75°C e ventoinha operando conforme curva PWM configurada.',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Temperatura da CPU estabilizada em 39°C em repouso e 68°C em estresse. Fan operando a 1.950 RPM com fluxo de ar desobstruído e pasta térmica em perfeito estado.',
            'resultado_anomalia' => 'SUPERAQUECIMENTO CRÍTICO: Temperatura da CPU disparou para 99°C com acionamento de Thermal Throttling e desligamento térmico. Fan travado (0 RPM) ou pasta térmica ressecada com presilhas frouxas.'
        ],
        [
            'comp' => 'SSD',
            'nome' => 'Telemetria S.M.A.R.T. e Leitura de SSD',
            'tipo' => 'hardware',
            'descricao' => 'Lê o estado de saúde das células NAND Flash, percentual de vida útil restante (TBW), blocos reservas e taxa de leitura NVMe/SATA.',
            'icone' => 'ssd.png',
            'comando_ou_acao' => 'CrystalDiskInfo / smartctl com teste de leitura sequencial e aleatória de blocos',
            'resultado_esperado' => 'Saúde do SSD em 95-100%, 0 blocos corrompidos e taxa de transferência dentro das especificações.',
            'tempo_estimado' => '3 minutos',
            'resultado_normal' => 'Status de Saúde: 100% Saudável. 0 blocos reservas utilizados, temperatura do controlador em 38°C e velocidade de leitura estável em 540 MB/s.',
            'resultado_anomalia' => 'FALHA DE COMUNICAÇÃO SSD: Blocos defeituosos na memória NAND Flash, desgaste crítico (0% vida útil) e erros de timeout I/O na controladora SATA/NVMe.'
        ],
        [
            'comp' => 'HD',
            'nome' => 'Inspeção de Superfície e S.M.A.R.T. do HD',
            'tipo' => 'hardware',
            'descricao' => 'Executa leitura de superfície magnética para verificar a saúde dos pratos, contagem de setores realocados (05) e ruídos no atuador mecânico.',
            'icone' => 'hd.webp',
            'comando_ou_acao' => 'Varredura de superfície HD Tune / smartctl -t long /dev/sda',
            'resultado_esperado' => '0 setores pendentes de realocação (Current Pending Sector Count = 0) e sem ruídos mecânicos.',
            'tempo_estimado' => '10 minutos',
            'resultado_normal' => 'Superfície magnética 100% íntegra. 0 setores realocados, velocidade de rotação nominal (7200 RPM) e calibração de cabeçote silenciosa.',
            'resultado_anomalia' => 'BAD SECTORS DETECTADOS: Múltiplos setores defeituosos no início do disco (Atributos 05 e C5 em Alerta Vermelho). Ruídos mecânicos de batida de agulha.'
        ],
        [
            'comp' => 'GPU',
            'nome' => 'Teste de Renderização 3D e VRAM da GPU',
            'tipo' => 'hardware',
            'descricao' => 'Renderiza malhas poligonais e shaders pesados para checar integridade da memória VRAM, VRM e identificar artefatos gráficos.',
            'icone' => 'placadevideo.png',
            'comando_ou_acao' => 'Execução de loop de renderização FurMark / 3DMark em resolução nativa',
            'resultado_esperado' => 'Renderização contínua sem artefatos visuais, distorção de cores, crash do driver ou superaquecimento.',
            'tempo_estimado' => '10 minutos',
            'resultado_normal' => 'Renderização 3D estável a 60+ FPS sem quedas de frames ou artefatos. Temperatura da GPU em 64°C e consumo nos conectores PCIe equilibrado.',
            'resultado_anomalia' => 'ARTEFATOS E CRASH DE VÍDEO: Linhas coloridas (glitches), polígonos distorcidos na tela e reset imediato do driver gráfico por falha nos módulos de VRAM ou solda BGA do chip GPU.'
        ],
        [
            'comp' => 'Placa-Mãe',
            'nome' => 'Diagnóstico de Barramentos da Placa-Mãe',
            'tipo' => 'hardware',
            'descricao' => 'Inspeciona tensões de VRM da placa-mãe, integridade dos slots PCIe, barramento chipset, portas USB e capacitores de filtragem.',
            'icone' => 'placamae.png',
            'comando_ou_acao' => 'Inspeção de telemetria dos sensores I/O e teste de loopback nas controladoras da placa-mãe',
            'resultado_esperado' => 'Todos os barramentos PCIe/Chipset respondendo nos endereços corretos sem falhas de clock ou capacitores avariados.',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Barramentos PCIe x16, SATA e USB operando com tensão correta. VRMs da placa-mãe a 48°C e ausência de capacitores estufados ou curto no PCB.',
            'resultado_anomalia' => 'FALHA DE CHIPSET / VRM: Curto-circuito na linha de 12V do conector EPS/VRM ou falha de comunicação no chipset ponte sul. Barramentos de expansão inoperantes.'
        ],
        [
            'comp' => 'Limpeza',
            'nome' => 'Inspeção de Contatos e Poeira',
            'tipo' => 'hardware',
            'descricao' => 'Avalia a resistividade e condutividade dos slots de memória/PCIe, obstrução de dutos de ar e presença de poeira condutiva.',
            'icone' => 'broom.png',
            'comando_ou_acao' => 'Inspeção visual e teste de continuidade elétrica nos pinos de contato com álcool isopropílico/limpa contato',
            'resultado_esperado' => 'Slots limpos, sem fuligem, oxidação ou poeira bloqueando a dissipação e o contato elétrico.',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Contatos dourados dos slots e componentes limpos, sem oxidação ou resíduos. Grades de ventilação do gabinete totalmente desobstruídas.',
            'resultado_anomalia' => 'CONTATOS OXIDADOS / EXCESSO DE POEIRA: Camada espessa de poeira isolando os contatos dos slots RAM/PCIe e obstruindo as aletas de ventilação, causando mau contato elétrico.'
        ],
        [
            'comp' => 'Monitor',
            'nome' => 'Teste de Sinal de Vídeo e Monitor',
            'tipo' => 'hardware',
            'descricao' => 'Verifica sinal de sincronismo HDMI/DisplayPort, alimentação do monitor, painel de LED e integridade do cabo de vídeo.',
            'icone' => 'monitor.png',
            'comando_ou_acao' => 'Teste de padrão de cores RGB, taxa de atualização e sinal EDID de comunicação digital DDC/CI',
            'resultado_esperado' => 'Comunicação EDID perfeita, painel com iluminação uniforme e sem perda de pacotes de sinal.',
            'tempo_estimado' => '3 minutos',
            'resultado_normal' => 'Monitor recebendo sinal nativo Full HD/4K 60Hz. Cabo HDMI/DisplayPort íntegro, sem dead pixels, flickering ou perda de sinal.',
            'resultado_anomalia' => 'SEM SINAL / CABO DANIFICADO: Falha de comunicação digital (Sem Sinal no display). Cabo de vídeo rompido/danificado ou placa lógica do monitor sem alimentação.'
        ],

        // --- 2. TESTES DE SOFTWARE (4 testes) ---
        [
            'comp' => 'Reinstalar SO',
            'nome' => 'Verificação de Integridade do Sistema (SFC / DISM)',
            'tipo' => 'software',
            'descricao' => 'Executa varredura detalhada de hashes criptográficos dos arquivos críticos do kernel, bibliotecas DLL e registro do sistema operacional.',
            'icone' => 'reinstall.png',
            'comando_ou_acao' => 'sfc /scannow && DISM /Online /Cleanup-Image /RestoreHealth',
            'resultado_esperado' => 'A Proteção de Recursos não encontrou nenhuma violação de integridade nos arquivos de sistema.',
            'tempo_estimado' => '8 minutos',
            'resultado_normal' => 'A Proteção de Recursos do Windows concluiu a análise: 0 arquivos de sistema corrompidos ou ausentes no repositório WinSxS. Kernel operacional íntegro.',
            'resultado_anomalia' => 'CORRUPÇÃO CRÍTICA DO SISTEMA OPERACIONAL: Arquivos essenciais de boot e bibliotecas do kernel (ntoskrnl.exe / winload.efi) corrompidos e irrecuperáveis. Causa direta de Tela Azul (BSOD).'
        ],
        [
            'comp' => 'Atualização',
            'nome' => 'Auditoria de Drivers e Windows Update',
            'tipo' => 'software',
            'descricao' => 'Inspeciona o Gerenciador de Dispositivos por drivers com código de erro (Code 43/10), assinaturas corrompidas e pacotes de atualização.',
            'icone' => 'update.png',
            'comando_ou_acao' => 'driverquery /v /fo list && usoclient StartInteractiveScan',
            'resultado_esperado' => 'Todos os adaptadores operando com drivers válidos, assinados e atualizados.',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Todos os dispositivos de hardware estão com drivers certificados e assinados digitalmente instalados, sem conflitos ou códigos de erro no Gerenciador de Dispositivos.',
            'resultado_anomalia' => 'DRIVER DE VÍDEO CORROMPIDO / DESATUALIZADO: Driver da placa de vídeo em estado de falha (Código de Erro 43). O sistema reverteu para o driver básico padrão do Windows em resolução 800x600.'
        ],
        [
            'comp' => 'BIOS',
            'nome' => 'Auditoria de Configurações da BIOS/UEFI',
            'tipo' => 'software',
            'descricao' => 'Analisa ordem de inicialização (Boot Order), perfil XMP/EXPO de memória, versão do firmware UEFI e integridade da bateria CMOS.',
            'icone' => 'bios.png',
            'comando_ou_acao' => 'Leitura da tabela SMBIOS via WMI / dmidecode e conferência de NVRAM',
            'resultado_esperado' => 'Firmware íntegro, data/hora preservadas e perfil de inicialização UEFI Secure Boot ativado.',
            'tempo_estimado' => '3 minutos',
            'resultado_normal' => 'Firmware UEFI íntegro na versão mais recente. Ordem de boot correta (unidade primária), perfil XMP estável e relógio RTC/CMOS sincronizado.',
            'resultado_anomalia' => 'FALHA DE CONFIGURAÇÃO DE BIOS: Ordem de boot corrompida (unidade de boot ausente), data/hora resetada para 01/01/2000 por bateria CMOS descarregada ou perfil de memória com voltagem incorreta.'
        ],
        [
            'comp' => 'Reinstalar SO',
            'nome' => 'Diagnóstico de Inicialização e Serviços do Sistema',
            'tipo' => 'software',
            'descricao' => 'Analisa a cadeia de inicialização de serviços do SO, dependências de inicialização e logs de dump do Event Viewer (BugCheck).',
            'icone' => 'reinstall.png',
            'comando_ou_acao' => 'Get-Service | Where-Object {$_.Status -ne "Running"} && wevtutil qe System /q:"*[System[(Level=1 or Level=2)]]" /f:text /c:5',
            'resultado_esperado' => 'Serviços essenciais em execução e 0 eventos críticos de BugCheck nos logs de inicialização.',
            'tempo_estimado' => '4 minutos',
            'resultado_normal' => 'Todos os serviços críticos do sistema inicializados com sucesso. Nenhum log de falha de carregamento ou falha de dependência de driver.',
            'resultado_anomalia' => 'CRASH NA INICIALIZAÇÃO DO SO: Múltiplos serviços críticos em timeout, falha no carregamento do ntoskrnl.exe e registro de despejo de memória (Crash Dump / BSOD).'
        ],

        // --- 3. TESTES DE REDE (2 testes) ---
        [
            'comp' => 'Reinstalar SO',
            'nome' => 'Diagnóstico da Pilha de Rede TCP/IP e Resolução DNS',
            'tipo' => 'rede',
            'descricao' => 'Testa conectividade de rede, resolução de nomes DNS, latência ICMP/Ping e integridade dos sockets e pilha TCP/IP do sistema operacional.',
            'icone' => 'reinstall.png',
            'comando_ou_acao' => 'ping 8.8.8.8 -n 4 && netsh int ip reset && nslookup google.com',
            'resultado_esperado' => 'Resposta ICMP < 30ms, resolução DNS imediata e pilha de rede TCP/IP sem erros de protocolo.',
            'tempo_estimado' => '3 minutos',
            'resultado_normal' => 'Pilha de rede TCP/IP 100% funcional. Rota padrão estabelecida, latência de 14ms e resolução de nomes DNS instantânea sem perda de pacotes.',
            'resultado_anomalia' => 'FALHA NA PILHA DE REDE: Winsock e pilha TCP/IP corrompidos no sistema operacional. Falha na alocação de sockets de rede e resolução DNS inacessível.'
        ],
        [
            'comp' => 'Atualização',
            'nome' => 'Auditoria do Adaptador de Rede e Controlador Ethernet/Wi-Fi',
            'tipo' => 'rede',
            'descricao' => 'Verifica a controladora física de rede PCI-e/USB, negociação de link Gigabit e integridade do driver de interface de rede.',
            'icone' => 'update.png',
            'comando_ou_acao' => 'Get-NetAdapter | Get-NetAdapterHardwareInfo && ipconfig /all',
            'resultado_esperado' => 'Link Ethernet/Wi-Fi ativo a 1000 Mbps Full-Duplex e driver certificado sem erros no barramento.',
            'tempo_estimado' => '3 minutos',
            'resultado_normal' => 'Adaptador de rede Gigabit Ethernet detectado e conectado em 1.0 Gbps Full Duplex. Driver oficial atualizado e buffer de pacotes estável.',
            'resultado_anomalia' => 'DRIVER/ADAPTADOR DE REDE INOPERANTE: Driver de rede em falha de inicialização (Code 10/43) ou interface de rede incapaz de negociar conexão com o barramento.'
        ],

        // --- 4. TESTES DE SEGURANÇA (2 testes) ---
        [
            'comp' => 'Procurar Vírus',
            'nome' => 'Varredura Completa Antivírus e Rootkits',
            'tipo' => 'seguranca',
            'descricao' => 'Escaneia processos em memória, serviços ocultos, chaves Run de inicialização e pastas temporárias em busca de ameaças ativas.',
            'icone' => 'Virabot_shell.webp',
            'comando_ou_acao' => 'Varredura heurística profunda de assinaturas de malware em memória RAM, diretórios de sistema e registro',
            'resultado_esperado' => '0 ameaças ativas detectadas na memória e no sistema de arquivos.',
            'tempo_estimado' => '12 minutos',
            'resultado_normal' => 'Varredura de segurança concluída com sucesso: 0 vírus, trojans, mineradores de criptomoedas ou processos maliciosos detectados no sistema.',
            'resultado_anomalia' => 'MALWARE DE ALTO CONSUMO DETECTADO: Trojan.CoinMiner ativo em segundo plano consumindo 100% de CPU/RAM, injetando threads maliciosas e travando as operações do SO.'
        ],
        [
            'comp' => 'Procurar Vírus',
            'nome' => 'Auditoria de Rootkits e Injeção de Processos Ocultos',
            'tipo' => 'seguranca',
            'descricao' => 'Inspeciona hooks de API no kernel, injeção DLL em processos legítimos (svchost/winlogon) e portas de comunicação backdoor.',
            'icone' => 'Virabot_shell.webp',
            'comando_ou_acao' => 'Análise heurística de integridade do kernel e inspeção de processos ocultos com proteção anti-rootkit',
            'resultado_esperado' => '0 processos injetados ou ganchos (hooks) não autorizados no kernel do sistema.',
            'tempo_estimado' => '8 minutos',
            'resultado_normal' => 'Todos os processos do sistema validados com assinatura digital. Nenhuma injeção de código ou backdoor em execução no kernel.',
            'resultado_anomalia' => 'ROOTKIT/PROCESSO OCULTO EM EXECUÇÃO: Processo camuflado "winlogon_fake.exe" executando chamadas de sistema não autorizadas e minerando em segundo plano.'
        ],

        // --- 5. TESTES GERAIS (2 testes) ---
        [
            'comp' => 'Fonte',
            'nome' => 'Autoteste Global de Barramentos e Sequência POST',
            'tipo' => 'geral',
            'descricao' => 'Verifica a sequência de inicialização de energia da placa-mãe (POST - Power-On Self-Test), barramentos de alimentação ATX e comunicação primária entre CPU/Chipset.',
            'icone' => 'fonte4.png',
            'comando_ou_acao' => 'Diagnóstico de sequência de boot POST e leitura de códigos de status na placa de diagnóstico (Debug Card)',
            'resultado_esperado' => 'Código POST 00/AA indicando ciclo completo de energização e passagem de controle para o firmware UEFI.',
            'tempo_estimado' => '2 minutos',
            'resultado_normal' => 'Sequência POST concluída em 1.8 segundos com sucesso (Status: AA - Ready). Todas as linhas de alimentação da placa e barramentos ativos.',
            'resultado_anomalia' => 'FALHA DE ENERGIZAÇÃO NO POST (Código 00 / No Power): A placa-mãe não completou o ciclo de boot devido à ausência de alimentação na linha primária da fonte ATX (+12V/PG).'
        ],
        [
            'comp' => 'Cooler',
            'nome' => 'Teste de Estresse Geral e Carga Térmica do Sistema',
            'tipo' => 'geral',
            'descricao' => 'Aplica carga de trabalho sintética simultânea em todos os subsistemas (CPU, memória e barramentos) monitorando limites de temperatura e estabilidade.',
            'icone' => 'cooler.png',
            'comando_ou_acao' => 'Execução de teste de estabilidade global AIDA64 / OCCT System Stress Test por 5 minutos',
            'resultado_esperado' => 'Sistema operando estavelmente sob carga máxima com dissipação térmica dentro dos limites seguros (< 75°C).',
            'tempo_estimado' => '5 minutos',
            'resultado_normal' => 'Sistema estável após teste de estresse de carga total. Temperaturas estáveis e nenhum desligamento ou travamento por sobrecarga.',
            'resultado_anomalia' => 'FALHA POR INSTABILIDADE TÉRMICA: Sobrecarga térmica imediata nos primeiros 30 segundos de estresse. Temperatura ultrapassou 98°C, forçando desligamento de emergência por superaquecimento no dissipador.'
        ]
    ];

    $stmtCheck = $db->prepare("SELECT id FROM componentes_testes WHERE nome = :nome AND tipo = :tipo");
    $stmtInsert = $db->prepare("
        INSERT INTO componentes_testes (componente_id, nome, tipo, descricao, icone, comando_ou_acao, resultado_esperado, tempo_estimado, resultado_normal, resultado_anomalia)
        VALUES (:componente_id, :nome, :tipo, :descricao, :icone, :comando_ou_acao, :resultado_esperado, :tempo_estimado, :resultado_normal, :resultado_anomalia)
    ");
    $stmtUpdate = $db->prepare("
        UPDATE componentes_testes 
        SET componente_id = :componente_id,
            descricao = :descricao,
            icone = :icone,
            comando_ou_acao = :comando_ou_acao,
            resultado_esperado = :resultado_esperado,
            tempo_estimado = :tempo_estimado,
            resultado_normal = :resultado_normal,
            resultado_anomalia = :resultado_anomalia
        WHERE id = :id
    ");

    $inseridos = 0;
    $atualizados = 0;

    foreach ($testesSeed as $teste) {
        $compId = $mapaCompId[$teste['comp']] ?? null;

        $stmtCheck->execute([
            ':nome' => $teste['nome'],
            ':tipo' => $teste['tipo']
        ]);
        $idExistente = $stmtCheck->fetchColumn();

        if ($idExistente === false) {
            $stmtInsert->execute([
                ':componente_id' => $compId,
                ':nome' => $teste['nome'],
                ':tipo' => $teste['tipo'],
                ':descricao' => $teste['descricao'],
                ':icone' => $teste['icone'],
                ':comando_ou_acao' => $teste['comando_ou_acao'],
                ':resultado_esperado' => $teste['resultado_esperado'],
                ':tempo_estimado' => $teste['tempo_estimado'],
                ':resultado_normal' => $teste['resultado_normal'],
                ':resultado_anomalia' => $teste['resultado_anomalia']
            ]);
            $inseridos++;
        } else {
            $stmtUpdate->execute([
                ':id' => (int)$idExistente,
                ':componente_id' => $compId,
                ':descricao' => $teste['descricao'],
                ':icone' => $teste['icone'],
                ':comando_ou_acao' => $teste['comando_ou_acao'],
                ':resultado_esperado' => $teste['resultado_esperado'],
                ':tempo_estimado' => $teste['tempo_estimado'],
                ':resultado_normal' => $teste['resultado_normal'],
                ':resultado_anomalia' => $teste['resultado_anomalia']
            ]);
            $atualizados++;
        }
    }

    // Sincronizar sqlite_sequence para componentes_testes
    $db->exec("
        DELETE FROM sqlite_sequence WHERE name = 'componentes_testes';
        INSERT INTO sqlite_sequence (name, seq) VALUES ('componentes_testes', (SELECT COALESCE(MAX(id), 0) FROM componentes_testes));
    ");

    $db->commit();

    $totalHW = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'hardware'")->fetchColumn();
    $totalSW = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'software'")->fetchColumn();
    $totalRede = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'rede'")->fetchColumn();
    $totalSeg = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'seguranca'")->fetchColumn();
    $totalGeral = (int)$db->query("SELECT COUNT(*) FROM componentes_testes WHERE tipo = 'geral'")->fetchColumn();
    $totalTodos = (int)$db->query("SELECT COUNT(*) FROM componentes_testes")->fetchColumn();

    if ($isCli) {
        echo "\n======================================================\n";
        echo "   MIGRAÇÃO DE TESTES E DIAGNÓSTICO CONCLUÍDA       \n";
        echo "======================================================\n";
        echo " Tabela: componentes_testes atualizada com sucesso.\n";
        echo " Novos registros inseridos: {$inseridos}\n";
        echo " Registros atualizados: {$atualizados}\n";
        echo " - Hardware:   {$totalHW}\n";
        echo " - Software:   {$totalSW}\n";
        echo " - Rede:       {$totalRede}\n";
        echo " - Segurança:  {$totalSeg}\n";
        echo " - Geral:      {$totalGeral}\n";
        echo " TOTAL GERAL:  {$totalTodos}\n";
        echo " Sincronização de sqlite_sequence concluída.\n";
        echo "======================================================\n\n";
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($isCli) {
        echo "Erro na migração componentes_testes: " . $e->getMessage() . "\n";
    }
    throw $e;
}
