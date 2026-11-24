<?php
use VanillIA\Core\I18n;

$lang = $lang ?? I18n::getLang();
$theme = $theme ?? I18n::getTheme();
$pctQuota = $user['credits_monthly_limit'] > 0
    ? min(100, max(0, (int)round(($user['credits_balance'] / $user['credits_monthly_limit']) * 100)))
    : 0;
$barColor = $pctQuota > 35 ? 'bg-success' : ($pctQuota > 15 ? 'bg-warning' : 'bg-danger');
$hljsHref = $theme === 'light'
    ? 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.9.0/styles/github.min.css'
    : 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.9.0/styles/github-dark.min.css';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(I18n::t('chat_title')) ?></title>
    <script>
        (function () {
            const saved = localStorage.getItem('vanillia_theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', saved);
            }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link id="hljsTheme" href="<?= $hljsHref ?>" rel="stylesheet">

    <style>
        :root[data-bs-theme="dark"] {
            --app-bg: #121316;
            --card-bg: #1c1e24;
            --card-border: rgba(255, 255, 255, 0.08);
            --card-hover-border: rgba(255, 255, 255, 0.22);
            --input-bg: #22252c;
            --code-bg: #0d0e11;
            --progress-bg: #2b303b;
            --sidebar-hover: rgba(255, 255, 255, 0.06);
            --pinned-bg: rgba(255, 193, 7, 0.05);
        }
        :root[data-bs-theme="light"] {
            --app-bg: #f4f6f9;
            --card-bg: #ffffff;
            --card-border: rgba(15, 23, 42, 0.10);
            --card-hover-border: rgba(15, 23, 42, 0.24);
            --input-bg: #ffffff;
            --code-bg: #f8fafc;
            --progress-bg: #e2e8f0;
            --sidebar-hover: rgba(15, 23, 42, 0.05);
            --pinned-bg: rgba(255, 193, 7, 0.10);
        }
        body {
            background-color: var(--app-bg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            height: 100dvh;
            overflow: hidden;
            transition: background-color 0.2s ease;
        }
        .chat-container { max-width: 840px; margin: 0 auto; width: 100%; }

        .model-select {
            max-width: 145px;
            text-overflow: ellipsis;
        }
        @media (min-width: 576px) {
            .model-select { max-width: 230px; }
        }
        @media (min-width: 768px) {
            .model-select { max-width: 280px; }
        }

        .exchange-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            transition: all 0.2s ease;
        }
        .exchange-card.collapsed-view {
            padding: 10px 16px !important;
            cursor: pointer;
            opacity: 0.82;
        }
        .exchange-card.collapsed-view:hover {
            opacity: 1;
            border-color: var(--card-hover-border);
        }
        .exchange-card.collapsed-view .card-full-body { display: none !important; }
        .exchange-card:not(.collapsed-view) .card-collapsed-header { display: none !important; }

        .prompt-box {
            background-color: var(--input-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 8px 12px;
        }
        .prompt-box:focus-within {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
        }
        .prompt-box textarea {
            background: transparent;
            border: none;
            outline: none;
            resize: none;
            color: var(--bs-body-color);
            max-height: 150px;
        }
        .prompt-box textarea::placeholder {
            color: var(--bs-secondary-color);
        }
        .command-pills { overflow-x: auto; white-space: nowrap; scrollbar-width: none; }
        .command-pills::-webkit-scrollbar { display: none; }
        .markdown-body pre {
            background: var(--code-bg);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            overflow-x: auto;
        }
        .markdown-body p:last-child { margin-bottom: 0; }

        .conv-item {
            transition: background-color 0.15s ease;
        }
        .conv-item:hover, .conv-item.active-conv {
            background-color: var(--sidebar-hover) !important;
        }
        .conv-item .conv-actions {
            opacity: 0.65;
            transition: opacity 0.15s ease;
        }
        .conv-item:hover .conv-actions {
            opacity: 1;
        }
        .conv-item[data-pinned="1"] {
            border-left: 2px solid #ffc107 !important;
            background-color: var(--pinned-bg) !important;
        }
    </style>
</head>
<body class="d-flex flex-column">

    <!-- HEADER COCKPIT RESPONSIVE -->
    <header class="border-bottom border-secondary border-opacity-25 bg-body px-2 px-md-3 py-2 d-flex align-items-center justify-content-between gap-2 flex-nowrap">

        <!-- Partie Gauche : Menu Historique + Logo + Modèle + Bouton Nouveau -->
        <div class="d-flex align-items-center gap-1 gap-sm-2 min-w-0 flex-grow-1">
            <button class="btn btn-sm btn-outline-secondary border-0 flex-shrink-0" data-bs-toggle="offcanvas" data-bs-target="#sidebarHistory" title="<?= htmlspecialchars(I18n::t('chat_history')) ?>">
                <i class="bi bi-layout-sidebar fs-5"></i>
            </button>

            <span class="fw-bold d-none d-lg-inline me-1 flex-shrink-0">Vanill-<span class="text-primary">IA</span> <span class="fw-normal text-body-secondary">Chat</span></span>

            <select id="modelSelector" class="form-select form-select-sm bg-body-tertiary border-secondary border-opacity-25 fw-medium model-select">
                <?php foreach ($models as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $m['is_default'] ? 'selected' : '' ?>>
                        ✦ <?= htmlspecialchars($m['display_name']) ?><?= ($m['min_role'] ?? 'user') === 'admin' ? ' [Admin]' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-sm btn-outline-primary border-0 flex-shrink-0 px-2" onclick="startNewConversation()" title="<?= htmlspecialchars(I18n::t('chat_new_tooltip')) ?>">
                <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline"><?= htmlspecialchars(I18n::t('chat_new')) ?></span>
            </button>
        </div>

        <!-- Partie Droite : Jauge Quota + Langue + Thème + Actions -->
        <div class="d-flex align-items-center gap-1 gap-sm-2 flex-shrink-0">

            <!-- Jauge Quota en % -->
            <div class="d-flex flex-column text-end me-1 px-2 py-1 rounded" role="button" onclick="openMyUsageModal()" title="<?= htmlspecialchars(I18n::t('chat_quota_tooltip')) ?>">
                <div class="d-flex align-items-center gap-1 justify-content-end">
                    <i class="bi bi-bar-chart-line text-primary small"></i>
                    <span class="small text-body-secondary d-none d-sm-inline" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('chat_quota')) ?></span>
                    <span id="quotaText" class="small fw-bold" style="font-size: 0.8rem;"><?= $pctQuota ?> %</span>
                </div>
                <div class="progress mt-1" style="height: 4px; width: 65px; background-color: var(--progress-bg);">
                    <div id="quotaBar" class="progress-bar <?= $barColor ?>" style="width: <?= $pctQuota ?>%"></div>
                </div>
            </div>

            <!-- Sélecteur Langue FR / EN + Switch Thème (Desktop & Tablet) -->
            <div class="d-none d-md-flex align-items-center gap-1 me-1">
                <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                    <button type="button" class="btn <?= $lang === 'fr' ? 'btn-primary' : 'btn-outline-secondary' ?> py-0 px-2" style="font-size: 0.75rem;" onclick="switchLanguage('fr')">FR</button>
                    <button type="button" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?> py-0 px-2" style="font-size: 0.75rem;" onclick="switchLanguage('en')">EN</button>
                </div>
                <button type="button" id="themeToggleBtn" class="btn btn-sm btn-outline-secondary border-0 rounded-circle" onclick="toggleTheme()" title="<?= htmlspecialchars(I18n::t('theme_toggle_title')) ?>">
                    <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?> fs-6"></i>
                </button>
            </div>

            <!-- Boutons Desktop -->
            <div class="d-none d-md-flex align-items-center gap-1">
                <button class="btn btn-sm btn-outline-warning border-0 rounded-circle text-warning" onclick="openConvPromptModal()" title="<?= htmlspecialchars(I18n::t('chat_conv_prompt_title')) ?>">
                    <i class="bi bi-sliders2-vertical fs-5"></i>
                </button>

                <button id="exportConvBtnDesktop" class="btn btn-sm btn-outline-secondary border-0 rounded-circle d-none" onclick="exportCurrentConversation()" title="<?= htmlspecialchars(I18n::t('chat_export_md_title')) ?>">
                    <i class="bi bi-download fs-5"></i>
                </button>

                <button class="btn btn-sm btn-outline-secondary border-0 rounded-circle" onclick="openMemoriesModal()" title="<?= htmlspecialchars(I18n::t('chat_memories_title')) ?>">
                    <i class="bi bi-bookmark-heart fs-5"></i>
                </button>

                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin" class="btn btn-sm btn-outline-info border-0 rounded-circle" title="<?= htmlspecialchars(I18n::t('chat_admin_title')) ?>">
                        <i class="bi bi-gear-wide-connected fs-5"></i>
                    </a>
                <?php endif; ?>

                <a href="/logout" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="<?= htmlspecialchars(I18n::t('chat_logout')) ?>">
                    <i class="bi bi-power fs-5"></i>
                </a>
            </div>

            <!-- Menu Déroulant Mobile -->
            <div class="dropdown d-md-none">
                <button class="btn btn-sm btn-outline-secondary border-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg small">
                    <li class="px-3 py-2 d-flex align-items-center justify-content-between gap-2">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn <?= $lang === 'fr' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('fr')">FR</button>
                            <button type="button" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('en')">EN</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()" title="<?= htmlspecialchars(I18n::t('theme_toggle_title')) ?>">
                            <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?>" id="themeToggleIconMobile"></i>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item py-2" onclick="openMyUsageModal()">
                            <i class="bi bi-bar-chart-line text-info me-2"></i><?= htmlspecialchars(I18n::t('chat_my_usage')) ?>
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item py-2" onclick="openConvPromptModal()">
                            <i class="bi bi-sliders2-vertical text-warning me-2"></i><?= htmlspecialchars(I18n::t('chat_conv_prompt_menu')) ?>
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item py-2" onclick="openMemoriesModal()">
                            <i class="bi bi-bookmark-heart text-primary me-2"></i><?= htmlspecialchars(I18n::t('chat_memories_title')) ?>
                        </button>
                    </li>
                    <li id="exportConvBtnMobile" class="d-none">
                        <button class="dropdown-item py-2" onclick="exportCurrentConversation()">
                            <i class="bi bi-download text-success me-2"></i><?= htmlspecialchars(I18n::t('chat_export_md_menu')) ?>
                        </button>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-info py-2" href="/admin">
                                <i class="bi bi-gear-wide-connected me-2"></i><?= htmlspecialchars(I18n::t('chat_admin_title')) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger py-2" href="/logout">
                            <i class="bi bi-power me-2"></i><?= htmlspecialchars(I18n::t('chat_logout')) ?>
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </header>

    <!-- SIDEBAR OFFCANVAS -->
    <div class="offcanvas offcanvas-start bg-body" tabindex="-1" id="sidebarHistory" style="width: 320px;">
        <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
            <h6 class="offcanvas-title fw-bold mb-0">Vanill-<span class="text-primary">IA</span> Chat — <?= htmlspecialchars(I18n::t('chat_conversations')) ?></h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-2">
            <button class="btn btn-primary btn-sm w-100 mb-3 fw-semibold py-2" onclick="startNewConversation()" data-bs-dismiss="offcanvas">
                <i class="bi bi-plus-circle me-1"></i> <?= htmlspecialchars(I18n::t('chat_new_full')) ?>
            </button>
            <div id="conversationList" class="list-group list-group-flush flex-grow-1 overflow-y-auto gap-1">
                <?php foreach ($conversations as $conv):
                    $isPinned = (int)($conv['is_pinned'] ?? 0) === 1;
                ?>
                    <div class="list-group-item list-group-item-action bg-transparent border-0 rounded d-flex justify-content-between align-items-center py-2 px-2 conv-item"
                         data-id="<?= htmlspecialchars($conv['id']) ?>"
                         data-pinned="<?= $isPinned ? '1' : '0' ?>"
                         data-updated="<?= htmlspecialchars($conv['updated_at'] ?? '') ?>">
                        <div class="d-flex align-items-center text-truncate flex-grow-1 min-w-0 me-1 conv-main-area"
                             role="button"
                             onclick="loadConversation('<?= htmlspecialchars($conv['id']) ?>')"
                             ondblclick="startRenameConversation('<?= htmlspecialchars($conv['id']) ?>', event)">
                            <i class="bi <?= $isPinned ? 'bi-pin-angle-fill text-warning' : 'bi-chat-left-text text-body-secondary' ?> me-2 flex-shrink-0 conv-icon"></i>
                            <span class="text-truncate small conv-title-text" title="<?= htmlspecialchars($conv['title']) ?>"><?= htmlspecialchars($conv['title']) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0 conv-actions">
                            <button class="btn btn-sm btn-link p-0 px-1 <?= $isPinned ? 'text-warning' : 'text-body-secondary' ?> conv-pin-btn"
                                    onclick="togglePinConversation('<?= htmlspecialchars($conv['id']) ?>', event)"
                                    title="<?= htmlspecialchars($isPinned ? I18n::t('chat_unpin') : I18n::t('chat_pin')) ?>">
                                <i class="bi <?= $isPinned ? 'bi-pin-fill' : 'bi-pin-angle' ?> small"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-body-secondary p-0 px-1"
                                    onclick="startRenameConversation('<?= htmlspecialchars($conv['id']) ?>', event)"
                                    title="<?= htmlspecialchars(I18n::t('chat_rename')) ?>">
                                <i class="bi bi-pencil small"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-body-secondary p-0 px-1"
                                    onclick="deleteConversation('<?= htmlspecialchars($conv['id']) ?>', event)"
                                    title="<?= htmlspecialchars(I18n::t('chat_delete')) ?>">
                                <i class="bi bi-trash3 small"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="border-top border-secondary border-opacity-25 pt-2 mt-2 small text-body-secondary d-flex justify-content-between px-2">
                <span><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['display_name']) ?></span>
                <span class="badge bg-secondary bg-opacity-25 text-body"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
            </div>
        </div>
    </div>

    <!-- ZONE CENTRALE -->
    <main id="chatMain" class="flex-grow-1 overflow-y-auto px-2 px-sm-3 py-4">
        <div id="cardsContainer" class="chat-container d-flex flex-column gap-3">
            <div id="welcomeScreen" class="text-center my-5 py-4 px-2">
                <h2 class="fw-bold mb-2"><?= htmlspecialchars(I18n::t('chat_welcome_hello', ['name' => explode(' ', $user['display_name'])[0]])) ?></h2>
                <p class="text-body-secondary small"><?= htmlspecialchars(I18n::t('chat_welcome_sub')) ?></p>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="p-2 p-sm-3 chat-container">
        <div id="attachmentPreview" class="d-flex flex-wrap gap-2 mb-2 d-none"></div>

        <div class="command-pills d-flex gap-2 mb-2 pb-1">
            <button class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 border-opacity-25 text-nowrap small" onclick="injectPrompt(I18N.chat_pill_summarize_prompt)">
                <i class="bi bi-text-paragraph text-warning me-1"></i> <?= htmlspecialchars(I18n::t('chat_pill_summarize')) ?>
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 border-opacity-25 text-nowrap small" onclick="injectPrompt(I18N.chat_pill_debug_prompt)">
                <i class="bi bi-bug text-danger me-1"></i> <?= htmlspecialchars(I18n::t('chat_pill_debug')) ?>
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 border-opacity-25 text-nowrap small" onclick="injectPrompt(I18N.chat_pill_email_prompt)">
                <i class="bi bi-envelope text-info me-1"></i> <?= htmlspecialchars(I18n::t('chat_pill_email')) ?>
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 border-opacity-25 text-nowrap small" onclick="injectPrompt(I18N.chat_pill_table_prompt)">
                <i class="bi bi-table text-success me-1"></i> <?= htmlspecialchars(I18n::t('chat_pill_table')) ?>
            </button>
        </div>

        <div class="prompt-box d-flex align-items-end gap-2 shadow-sm">
            <input type="file" id="fileInput" class="d-none" multiple onchange="handleFileSelect(this.files)">
            <button class="btn btn-sm btn-link text-body-secondary p-1 border-0" onclick="document.getElementById('fileInput').click()" title="<?= htmlspecialchars(I18n::t('chat_attach_title')) ?>">
                <i class="bi bi-paperclip fs-5"></i>
            </button>
            <textarea id="promptInput" class="form-control form-control-sm" rows="1" placeholder="<?= htmlspecialchars(I18n::t('chat_input_placeholder')) ?>"></textarea>
            <button id="clearPromptBtn" class="btn btn-sm btn-link text-body-secondary p-1 border-0 d-none" onclick="clearPromptInput()" title="<?= htmlspecialchars(I18n::t('chat_clear_title')) ?>">
                <i class="bi bi-x-circle fs-5"></i>
            </button>
            <button id="sendBtn" class="btn btn-primary btn-sm rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;" onclick="sendMessage()">
                <i class="bi bi-arrow-up-short fs-4"></i>
            </button>
        </div>
    </footer>

    <!-- MODALE MA CONSOMMATION & QUOTA MENSUEL -->
    <div class="modal fade" id="myUsageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><i class="bi bi-bar-chart-line text-primary me-2"></i><?= htmlspecialchars(I18n::t('modal_usage_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="p-3 rounded bg-body-tertiary border border-secondary border-opacity-25 h-100">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('modal_usage_budget')) ?></div>
                                <div class="fs-6 fw-bold text-success mt-1"><span id="muBalance">...</span> / <span id="muLimit">...</span> EUR</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 rounded bg-body-tertiary border border-secondary border-opacity-25 h-100">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('modal_usage_last_reset')) ?></div>
                                <div class="fw-bold mt-1" id="muLastReset">...</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 rounded bg-body-tertiary border border-secondary border-opacity-25 h-100">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('modal_usage_next_reset')) ?></div>
                                <div class="fw-bold text-info mt-1" id="muNextReset">...</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-cpu text-info me-1"></i><?= htmlspecialchars(I18n::t('modal_usage_by_model')) ?></h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th><?= htmlspecialchars(I18n::t('col_model')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_requests')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_total')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_cost_eur')) ?></th>
                                </tr>
                            </thead>
                            <tbody id="muModelsBody"></tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-calendar3 text-warning me-1"></i><?= htmlspecialchars(I18n::t('modal_usage_daily')) ?></h6>
                    <div class="table-responsive" style="max-height: 240px;">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th><?= htmlspecialchars(I18n::t('col_date')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_requests')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_in')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_out')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_total')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_cost_eur')) ?></th>
                                </tr>
                            </thead>
                            <tbody id="muDailyBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE PROMPT DE CONVERSATION PERSONNALISÉ -->
    <div class="modal fade" id="convPromptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><i class="bi bi-sliders2-vertical text-warning me-2"></i><?= htmlspecialchars(I18n::t('modal_prompt_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary mb-2"><?= htmlspecialchars(I18n::t('modal_prompt_desc')) ?></p>
                    <textarea id="convSystemPromptInput" class="form-control bg-body-tertiary border-secondary border-opacity-25 small mb-3" rows="4" placeholder="<?= htmlspecialchars(I18n::t('modal_prompt_placeholder')) ?>"></textarea>
                    <button class="btn btn-warning btn-sm w-100 fw-semibold" onclick="saveConversationPrompt()" data-bs-dismiss="modal"><?= htmlspecialchars(I18n::t('modal_prompt_apply')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE DES SOUVENIRS -->
    <div class="modal fade" id="memoriesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><i class="bi bi-bookmark-heart text-primary me-2"></i><?= htmlspecialchars(I18n::t('modal_memories_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary mb-3"><?= htmlspecialchars(I18n::t('modal_memories_desc')) ?></p>
                    <div class="input-group input-group-sm mb-3">
                        <input type="text" id="newMemoryInput" class="form-control bg-body-tertiary border-secondary border-opacity-25" placeholder="<?= htmlspecialchars(I18n::t('modal_memories_placeholder')) ?>">
                        <button class="btn btn-primary" onclick="addManualMemory()"><?= htmlspecialchars(I18n::t('modal_memories_add')) ?></button>
                    </div>
                    <div id="memoriesList" class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.9/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.9.0/highlight.min.js"></script>

    <script>
        const I18N = <?= json_encode(I18n::all(), JSON_UNESCAPED_UNICODE) ?>;
        const CURRENT_LANG = <?= json_encode($lang) ?>;
        const LOCALE = CURRENT_LANG === 'en' ? 'en-US' : 'fr-FR';

        let currentConversationId = '';
        let currentConversationSystemPrompt = '';
        let pendingAttachments = [];
        let isStreaming = false;

        function syncThemeAssets(theme) {
            const iconDesktop = document.querySelector('#themeToggleBtn i');
            const iconMobile = document.getElementById('themeToggleIconMobile');
            const cls = theme === 'dark' ? 'bi bi-sun-fill fs-6' : 'bi bi-moon-stars-fill fs-6';
            if (iconDesktop) iconDesktop.className = cls;
            if (iconMobile) iconMobile.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';

            const hljsLink = document.getElementById('hljsTheme');
            if (hljsLink) {
                hljsLink.href = theme === 'light'
                    ? 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.9.0/styles/github.min.css'
                    : 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.9.0/styles/github-dark.min.css';
            }
        }
        syncThemeAssets(document.documentElement.getAttribute('data-bs-theme') || 'dark');

        async function toggleTheme() {
            const current = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('vanillia_theme', next);
            document.cookie = `vanillia_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
            syncThemeAssets(next);
            try {
                await fetch('/api/preferences', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: next })
                });
            } catch (e) {}
        }

        async function switchLanguage(lang) {
            document.cookie = `vanillia_lang=${lang}; path=/; max-age=31536000; SameSite=Lax`;
            try {
                await fetch('/api/preferences', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lang })
                });
            } catch (e) {}
            window.location.reload();
        }

        function highlightCodeBlocks(container = document) {
            if (typeof hljs !== 'undefined' && typeof hljs.highlightElement === 'function') {
                container.querySelectorAll('pre code').forEach(el => hljs.highlightElement(el));
            }
        }

        function formatMessageDateTime(rawDate) {
            if (!rawDate) return '';
            const normalized = String(rawDate).replace(' ', 'T');
            const d = new Date(normalized);
            if (isNaN(d.getTime())) return String(rawDate);
            const pad = n => String(n).padStart(2, '0');
            if (CURRENT_LANG === 'en') {
                return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }
            return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${I18N.date_at} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function nowSqlString() {
            const d = new Date();
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        }

        const tx = document.getElementById('promptInput');
        const clearBtn = document.getElementById('clearPromptBtn');

        function updateClearButtonVisibility() {
            if (tx.value.length > 0) {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }

        function clearPromptInput() {
            tx.value = '';
            tx.style.height = 'auto';
            updateClearButtonVisibility();
            tx.focus();
        }

        tx.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            updateClearButtonVisibility();
        });
        tx.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        window.addEventListener('dragover', e => e.preventDefault());
        window.addEventListener('drop', e => {
            e.preventDefault();
            if (e.dataTransfer.files.length) handleFileSelect(e.dataTransfer.files);
        });

        function toggleExportButtons(show) {
            const btnDesk = document.getElementById('exportConvBtnDesktop');
            const btnMob  = document.getElementById('exportConvBtnMobile');
            if (show) {
                btnDesk?.classList.remove('d-none');
                btnMob?.classList.remove('d-none');
            } else {
                btnDesk?.classList.add('d-none');
                btnMob?.classList.add('d-none');
            }
        }

        async function openMyUsageModal() {
            const modal = new bootstrap.Modal(document.getElementById('myUsageModal'));
            modal.show();

            document.getElementById('muDailyBody').innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_loading)}</td></tr>`;
            document.getElementById('muModelsBody').innerHTML = `<tr><td colspan="4" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_loading)}</td></tr>`;

            const res = await fetch('/api/quota/usage');
            const data = await res.json();
            if (!res.ok) return;

            document.getElementById('muBalance').textContent = data.user.balance_eur;
            document.getElementById('muLimit').textContent = data.user.limit_eur;
            document.getElementById('muLastReset').textContent = data.user.last_refill_at;
            document.getElementById('muNextReset').textContent = data.user.next_refill_at
                ? `${data.user.next_refill_at} (J-${data.user.days_until_reset})`
                : I18N.modal_usage_manual_only;

            const mBody = document.getElementById('muModelsBody');
            if (!data.by_model || data.by_model.length === 0) {
                mBody.innerHTML = `<tr><td colspan="4" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_none_30d)}</td></tr>`;
            } else {
                mBody.innerHTML = data.by_model.map(m => `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(m.model_name)}</td>
                        <td>${m.requests_count}</td>
                        <td class="text-info">${Number(m.tokens_total).toLocaleString(LOCALE)}</td>
                        <td class="text-success fw-semibold">${m.cost_eur} EUR</td>
                    </tr>
                `).join('');
            }

            const dBody = document.getElementById('muDailyBody');
            if (!data.daily || data.daily.length === 0) {
                dBody.innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_none_activity)}</td></tr>`;
            } else {
                dBody.innerHTML = data.daily.map(d => `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(d.usage_date)}</td>
                        <td>${d.requests_count}</td>
                        <td class="text-body-secondary">${Number(d.tokens_in).toLocaleString(LOCALE)}</td>
                        <td class="text-body-secondary">${Number(d.tokens_out).toLocaleString(LOCALE)}</td>
                        <td class="text-primary fw-semibold">${Number(d.tokens_total).toLocaleString(LOCALE)}</td>
                        <td class="text-success fw-semibold">${d.cost_eur} EUR</td>
                    </tr>
                `).join('');
            }
        }

        function injectPrompt(text) {
            tx.value = text + tx.value;
            tx.focus();
            tx.dispatchEvent(new Event('input'));
        }

        function collapseAllExistingCards() {
            document.querySelectorAll('#cardsContainer .exchange-card').forEach(card => {
                card.classList.add('collapsed-view');
            });
        }

        function createExchangeCard(userPrompt, attachments = [], aiInitialText = '', tokenInfo = '', userCreatedAt = '', aiCreatedAt = '') {
            const welcome = document.getElementById('welcomeScreen');
            if (welcome) welcome.remove();

            collapseAllExistingCards();

            const summaryTitle = userPrompt ? userPrompt.substring(0, 65) + (userPrompt.length > 65 ? '...' : '') : I18N.card_file_attached;
            const formattedUserDate = formatMessageDateTime(userCreatedAt || nowSqlString());
            const formattedAiDate   = aiCreatedAt ? formatMessageDateTime(aiCreatedAt) : '';

            const card = document.createElement('div');
            card.className = 'exchange-card p-3 shadow-sm';

            let attBadges = '';
            attachments.forEach(a => {
                attBadges += `<span class="badge bg-body-tertiary border border-secondary border-opacity-25 text-body-secondary p-2 me-1"><i class="bi bi-paperclip text-primary me-1"></i>${escapeHtml(a.original_name)}</span>`;
            });

            card.innerHTML = `
                <div class="card-collapsed-header d-flex align-items-center justify-content-between gap-2" onclick="this.parentElement.classList.remove('collapsed-view')">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <i class="bi bi-chevron-right text-body-secondary small"></i>
                        <span class="fw-medium small text-body-secondary text-truncate">${escapeHtml(summaryTitle)}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="text-body-secondary d-none d-sm-inline" style="font-size: 0.72rem;">${escapeHtml(formattedUserDate)}</span>
                        <span class="badge bg-secondary bg-opacity-25 text-body-secondary small">${escapeHtml(I18N.card_expand)}</span>
                    </div>
                </div>

                <div class="card-full-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                        <div class="d-flex align-items-start gap-2">
                            <div class="badge bg-primary bg-opacity-25 text-primary rounded-circle p-2"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="small fw-bold">${escapeHtml(I18N.card_you)}</span>
                                    <span class="text-body-secondary user-msg-time" style="font-size: 0.72rem;" title="${escapeHtml(I18N.card_sent_at)}">
                                        <i class="bi bi-clock me-1"></i>${escapeHtml(formattedUserDate)}
                                    </span>
                                </div>
                                <div class="text-body-secondary mt-1 small" style="white-space: pre-wrap;">${escapeHtml(userPrompt)}</div>
                                ${attBadges ? `<div class="mt-2">${attBadges}</div>` : ''}
                            </div>
                        </div>
                        <button class="btn btn-sm btn-link text-body-secondary p-0 text-decoration-none" onclick="this.closest('.exchange-card').classList.add('collapsed-view')" title="${escapeHtml(I18N.card_collapse)}">
                            <i class="bi bi-arrows-collapse"></i>
                        </button>
                    </div>

                    <div class="d-flex align-items-start gap-2">
                        <div class="badge bg-info bg-opacity-25 text-info rounded-circle p-2"><i class="bi bi-stars"></i></div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="small fw-bold ai-model-label">AI</span>
                                    <span class="text-body-secondary ai-msg-time" style="font-size: 0.72rem;" title="${escapeHtml(I18N.card_received_at)}">
                                        ${formattedAiDate ? `<i class="bi bi-clock me-1"></i>${escapeHtml(formattedAiDate)}` : ''}
                                    </span>
                                </div>
                                <span class="small text-body-secondary font-monospace ai-cost-label" style="font-size: 0.75rem;">${escapeHtml(tokenInfo)}</span>
                            </div>
                            <div class="mt-2 small lh-base markdown-body ai-response-body">${aiInitialText ? renderMarkdown(aiInitialText) : `<span class="text-body-secondary fst-italic">${escapeHtml(I18N.card_thinking)}</span>`}</div>
                            <div class="d-flex gap-2 pt-2 mt-2 border-top border-secondary border-opacity-10">
                                <button class="btn btn-sm btn-outline-secondary border-0 text-body-secondary py-0 px-2" onclick="copyCardResponse(this)">
                                    <i class="bi bi-copy me-1"></i> ${escapeHtml(I18N.card_copy)}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('cardsContainer').appendChild(card);
            scrollToBottom();
            return card;
        }

        function buildSidebarItemHtml(id, title, isPinned = false) {
            const iconCls = isPinned ? 'bi-pin-angle-fill text-warning' : 'bi-chat-left-text text-body-secondary';
            const pinBtnCls = isPinned ? 'text-warning' : 'text-body-secondary';
            const pinIconCls = isPinned ? 'bi-pin-fill' : 'bi-pin-angle';
            const pinTitle = isPinned ? I18N.chat_unpin : I18N.chat_pin;

            return `
                <div class="d-flex align-items-center text-truncate flex-grow-1 min-w-0 me-1 conv-main-area"
                     role="button"
                     onclick="loadConversation('${escapeHtml(id)}')"
                     ondblclick="startRenameConversation('${escapeHtml(id)}', event)">
                    <i class="bi ${iconCls} me-2 flex-shrink-0 conv-icon"></i>
                    <span class="text-truncate small conv-title-text" title="${escapeHtml(title)}">${escapeHtml(title)}</span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0 conv-actions">
                    <button class="btn btn-sm btn-link p-0 px-1 ${pinBtnCls} conv-pin-btn"
                            onclick="togglePinConversation('${escapeHtml(id)}', event)"
                            title="${escapeHtml(pinTitle)}">
                        <i class="bi ${pinIconCls} small"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-body-secondary p-0 px-1"
                            onclick="startRenameConversation('${escapeHtml(id)}', event)"
                            title="${escapeHtml(I18N.chat_rename)}">
                        <i class="bi bi-pencil small"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-body-secondary p-0 px-1"
                            onclick="deleteConversation('${escapeHtml(id)}', event)"
                            title="${escapeHtml(I18N.chat_delete)}">
                        <i class="bi bi-trash3 small"></i>
                    </button>
                </div>
            `;
        }

        function sortConversationList() {
            const list = document.getElementById('conversationList');
            if (!list) return;
            const items = Array.from(list.querySelectorAll('.conv-item'));
            items.sort((a, b) => {
                const pinA = parseInt(a.dataset.pinned || '0', 10);
                const pinB = parseInt(b.dataset.pinned || '0', 10);
                if (pinA !== pinB) return pinB - pinA;
                const dateA = a.dataset.updated || '';
                const dateB = b.dataset.updated || '';
                return dateB.localeCompare(dateA);
            });
            items.forEach(el => list.appendChild(el));
        }

        function bumpConversationInSidebar(convId, title = null, updatedAt = null) {
            const list = document.getElementById('conversationList');
            if (!list) return;
            const ts = updatedAt || nowSqlString();
            let item = list.querySelector(`.conv-item[data-id="${convId}"]`);

            if (!item && title) {
                item = document.createElement('div');
                item.className = 'list-group-item list-group-item-action bg-transparent border-0 rounded d-flex justify-content-between align-items-center py-2 px-2 conv-item active-conv';
                item.dataset.id = convId;
                item.dataset.pinned = '0';
                item.dataset.updated = ts;
                item.innerHTML = buildSidebarItemHtml(convId, title, false);
                list.prepend(item);
            } else if (item) {
                item.dataset.updated = ts;
            }

            highlightActiveConversation(convId);
            sortConversationList();
        }

        function highlightActiveConversation(convId) {
            document.querySelectorAll('#conversationList .conv-item').forEach(el => {
                el.classList.toggle('active-conv', el.dataset.id === convId);
            });
        }

        async function togglePinConversation(convId, event) {
            if (event) event.stopPropagation();
            const item = document.querySelector(`.conv-item[data-id="${convId}"]`);
            if (!item) return;

            const res = await fetch(`/api/conversations/${convId}/pin`, { method: 'POST' });
            const data = await res.json();
            if (!res.ok) return;

            const isPinned = parseInt(data.is_pinned, 10) === 1;
            item.dataset.pinned = isPinned ? '1' : '0';

            const currentTitle = item.querySelector('.conv-title-text')?.textContent || 'Conversation';
            item.innerHTML = buildSidebarItemHtml(convId, currentTitle, isPinned);
            sortConversationList();
        }

        function startRenameConversation(convId, event) {
            if (event) event.stopPropagation();
            const item = document.querySelector(`.conv-item[data-id="${convId}"]`);
            if (!item) return;

            const titleSpan = item.querySelector('.conv-title-text');
            if (!titleSpan) return;

            const oldTitle = titleSpan.textContent.trim();
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm bg-body-tertiary border-primary py-0 px-2 small';
            input.value = oldTitle;
            input.maxLength = 120;
            input.onclick = e => e.stopPropagation();

            let finished = false;
            const finishRename = async (save) => {
                if (finished) return;
                finished = true;
                const newTitle = input.value.trim();
                const isPinned = item.dataset.pinned === '1';

                if (!save || !newTitle || newTitle === oldTitle) {
                    item.innerHTML = buildSidebarItemHtml(convId, oldTitle, isPinned);
                    return;
                }

                item.innerHTML = buildSidebarItemHtml(convId, newTitle, isPinned);
                await fetch(`/api/conversations/${convId}/rename`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title: newTitle })
                });
            };

            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    finishRename(true);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    finishRename(false);
                }
            });
            input.addEventListener('blur', () => finishRename(true));

            titleSpan.replaceWith(input);
            input.focus();
            input.select();
        }

        async function handleFileSelect(fileList) {
            const preview = document.getElementById('attachmentPreview');
            preview.classList.remove('d-none');

            for (const file of fileList) {
                const formData = new FormData();
                formData.append('file', file);

                try {
                    const res = await fetch('/api/upload', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || I18N.card_upload_error);

                    pendingAttachments.push(data.file);
                    renderPendingAttachments();
                } catch (err) {
                    alert(err.message);
                }
            }
        }

        function renderPendingAttachments() {
            const preview = document.getElementById('attachmentPreview');
            if (pendingAttachments.length === 0) {
                preview.classList.add('d-none');
                preview.innerHTML = '';
                return;
            }
            preview.innerHTML = pendingAttachments.map((f, idx) => `
                <span class="badge bg-body-tertiary border border-primary border-opacity-50 text-body p-2 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check text-primary"></i> ${escapeHtml(f.original_name)}
                    <i class="bi bi-x-circle text-danger" role="button" onclick="removeAttachment(${idx})"></i>
                </span>
            `).join('');
        }

        function removeAttachment(idx) {
            pendingAttachments.splice(idx, 1);
            renderPendingAttachments();
        }

        async function sendMessage() {
            if (isStreaming) return;
            const prompt = tx.value.trim();
            if (!prompt && pendingAttachments.length === 0) return;

            isStreaming = true;
            document.getElementById('sendBtn').disabled = true;

            const currentFiles = [...pendingAttachments];
            const attachmentIds = currentFiles.map(f => f.id);
            pendingAttachments = [];
            renderPendingAttachments();

            tx.value = '';
            tx.style.height = 'auto';
            updateClearButtonVisibility();

            const modelId = document.getElementById('modelSelector').value;
            const sentTimeSql = nowSqlString();
            const card = createExchangeCard(prompt, currentFiles, '', '', sentTimeSql, '');
            const responseBody = card.querySelector('.ai-response-body');
            const modelLabel = card.querySelector('.ai-model-label');
            const costLabel = card.querySelector('.ai-cost-label');
            const userTimeEl = card.querySelector('.user-msg-time');
            const aiTimeEl = card.querySelector('.ai-msg-time');

            let accumulatedText = '';

            try {
                const response = await fetch('/api/chat/stream', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        prompt: prompt,
                        conversation_id: currentConversationId,
                        model_id: modelId,
                        system_prompt: currentConversationSystemPrompt,
                        attachments: attachmentIds
                    })
                });

                if (!response.ok) {
                    const err = await response.json();
                    throw new Error(err.error || I18N.card_server_error);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let sseBuffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    sseBuffer += decoder.decode(value, { stream: true });
                    const events = sseBuffer.split('\n\n');
                    sseBuffer = events.pop();

                    for (const evtBlock of events) {
                        const lines = evtBlock.split('\n');
                        let eventType = 'message';
                        let dataStr = '';

                        for (const line of lines) {
                            if (line.startsWith('event: ')) eventType = line.substring(7).trim();
                            if (line.startsWith('data: ')) dataStr = line.substring(6).trim();
                        }

                        if (!dataStr) continue;
                        const payload = JSON.parse(dataStr);

                        if (eventType === 'meta') {
                            currentConversationId = payload.conversation_id;
                            modelLabel.textContent = payload.model_name;
                            toggleExportButtons(true);

                            if (payload.user_created_at && userTimeEl) {
                                userTimeEl.innerHTML = `<i class="bi bi-clock me-1"></i>${escapeHtml(formatMessageDateTime(payload.user_created_at))}`;
                            }
                            bumpConversationInSidebar(payload.conversation_id, payload.title, payload.updated_at);
                        } else if (eventType === 'delta') {
                            accumulatedText += payload.text;
                            responseBody.innerHTML = renderMarkdown(accumulatedText);
                            scrollToBottom();
                        } else if (eventType === 'memory_saved') {
                            costLabel.innerHTML = `<span class="badge bg-primary bg-opacity-25 text-primary me-2">${escapeHtml(I18N.card_memory_saved)}</span>`;
                        } else if (eventType === 'done') {
                            responseBody.innerHTML = renderMarkdown(accumulatedText);
                            costLabel.innerHTML += `${payload.tokens_in + payload.tokens_out} ${I18N.card_tokens}`;
                            if (aiTimeEl) {
                                const aiTs = payload.ai_created_at || nowSqlString();
                                aiTimeEl.innerHTML = `<i class="bi bi-clock me-1"></i>${escapeHtml(formatMessageDateTime(aiTs))}`;
                                bumpConversationInSidebar(currentConversationId, null, aiTs);
                            }
                            updateQuotaDisplay(payload.credits_balance, payload.credits_limit);
                            highlightCodeBlocks(responseBody);
                        } else if (eventType === 'error') {
                            responseBody.innerHTML = `<div class="alert alert-danger py-2 small mb-0">${escapeHtml(payload.message)}</div>`;
                        }
                    }
                }
            } catch (err) {
                responseBody.innerHTML = `<div class="alert alert-danger py-2 small mb-0">${escapeHtml(err.message)}</div>`;
            } finally {
                isStreaming = false;
                document.getElementById('sendBtn').disabled = false;
            }
        }

        async function loadConversation(convId) {
            currentConversationId = convId;
            highlightActiveConversation(convId);
            toggleExportButtons(true);

            const offcanvasEl = document.getElementById('sidebarHistory');
            if (offcanvasEl) {
                const instance = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (instance) instance.hide();
            }

            const container = document.getElementById('cardsContainer');
            container.innerHTML = '';

            const res = await fetch(`/api/conversations/${convId}`);
            const data = await res.json();
            if (!res.ok) return;

            currentConversationSystemPrompt = data.conversation.system_prompt || '';
            document.getElementById('convSystemPromptInput').value = currentConversationSystemPrompt;

            const msgs = data.messages || [];
            for (let i = 0; i < msgs.length; i++) {
                if (msgs[i].role === 'user') {
                    const userText = msgs[i].content;
                    const userDate = msgs[i].created_at || '';
                    const nextMsg = (msgs[i + 1] && msgs[i + 1].role === 'assistant') ? msgs[i + 1] : null;
                    const aiText = nextMsg ? nextMsg.content : '';
                    const aiDate = nextMsg ? (nextMsg.created_at || '') : '';
                    const tokens = nextMsg ? `${(parseInt(nextMsg.tokens_input || 0) + parseInt(nextMsg.tokens_output || 0))} ${I18N.card_tokens}` : '';
                    createExchangeCard(userText, [], aiText, tokens, userDate, aiDate);
                    if (nextMsg) i++;
                }
            }
            highlightCodeBlocks(container);
        }

        function exportCurrentConversation() {
            if (!currentConversationId) return;
            window.location.href = `/api/conversations/${currentConversationId}/export`;
        }

        function openConvPromptModal() {
            document.getElementById('convSystemPromptInput').value = currentConversationSystemPrompt;
            new bootstrap.Modal(document.getElementById('convPromptModal')).show();
        }

        async function saveConversationPrompt() {
            currentConversationSystemPrompt = document.getElementById('convSystemPromptInput').value.trim();
            if (currentConversationId) {
                await fetch(`/api/conversations/${currentConversationId}/prompt`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ system_prompt: currentConversationSystemPrompt })
                });
            }
        }

        async function deleteConversation(convId, event) {
            event.stopPropagation();
            if (!confirm(I18N.chat_delete_confirm)) return;
            await fetch(`/api/conversations/${convId}/delete`, { method: 'POST' });
            document.querySelector(`.conv-item[data-id="${convId}"]`)?.remove();
            if (currentConversationId === convId) startNewConversation();
        }

        function startNewConversation() {
            currentConversationId = '';
            currentConversationSystemPrompt = '';
            highlightActiveConversation('');
            toggleExportButtons(false);
            document.getElementById('cardsContainer').innerHTML = `
                <div id="welcomeScreen" class="text-center my-5 py-4 px-2">
                    <h2 class="fw-bold mb-2">${escapeHtml(I18N.chat_new_disc_title)}</h2>
                    <p class="text-body-secondary small">${escapeHtml(I18N.chat_new_disc_sub)}</p>
                </div>
            `;
        }

        function updateQuotaDisplay(balance, limit) {
            const pct = limit > 0 ? Math.min(100, Math.max(0, Math.round((balance / limit) * 100))) : 0;
            document.getElementById('quotaText').textContent = `${pct} %`;
            const bar = document.getElementById('quotaBar');
            bar.style.width = pct + '%';
            bar.className = 'progress-bar ' + (pct > 35 ? 'bg-success' : (pct > 15 ? 'bg-warning' : 'bg-danger'));
        }

        async function openMemoriesModal() {
            const modal = new bootstrap.Modal(document.getElementById('memoriesModal'));
            modal.show();
            await refreshMemoriesList();
        }

        async function refreshMemoriesList() {
            const res = await fetch('/api/memories');
            const data = await res.json();
            const list = document.getElementById('memoriesList');
            if (!data.memories || data.memories.length === 0) {
                list.innerHTML = `<div class="text-body-secondary small text-center py-3">${escapeHtml(I18N.modal_memories_empty)}</div>`;
                return;
            }
            list.innerHTML = data.memories.map(m => `
                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-body-tertiary border border-secondary border-opacity-10">
                    <div class="small pe-2">
                        <span class="badge ${m.source === 'auto' ? 'bg-info text-dark' : 'bg-secondary'} bg-opacity-25 text-body me-1">${m.source}</span>
                        ${escapeHtml(m.content)}
                    </div>
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteMemory(${m.id})"><i class="bi bi-trash"></i></button>
                </div>
            `).join('');
        }

        async function addManualMemory() {
            const input = document.getElementById('newMemoryInput');
            const content = input.value.trim();
            if (!content) return;
            await fetch('/api/memories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content })
            });
            input.value = '';
            await refreshMemoriesList();
        }

        async function deleteMemory(id) {
            await fetch(`/api/memories/${id}/delete`, { method: 'POST' });
            await refreshMemoriesList();
        }

        function copyCardResponse(btn) {
            const text = btn.closest('.flex-grow-1').querySelector('.ai-response-body').innerText;
            navigator.clipboard.writeText(text);
            btn.innerHTML = `<i class="bi bi-check2 text-success me-1"></i> ${escapeHtml(I18N.card_copied)}`;
            setTimeout(() => btn.innerHTML = `<i class="bi bi-copy me-1"></i> ${escapeHtml(I18N.card_copy)}`, 1800);
        }

        function renderMarkdown(md) {
            const cleanMd = (md || '').replace(/<memory_save>[\s\S]*?(?:<\/memory_save>|$)/gi, '').trim();
            return DOMPurify.sanitize(marked.parse(cleanMd));
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function scrollToBottom() {
            const main = document.getElementById('chatMain');
            main.scrollTop = main.scrollHeight;
        }
    </script>
</body>
</html>
