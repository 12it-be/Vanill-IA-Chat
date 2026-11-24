<?php
use VanillIA\Core\I18n;

$lang = $lang ?? I18n::getLang();
$theme = $theme ?? I18n::getTheme();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(I18n::t('admin_title')) ?></title>
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
    <style>
        :root[data-bs-theme="dark"] {
            --app-bg: #121316;
            --card-bg: #1c1e24;
            --card-border: rgba(255, 255, 255, 0.08);
        }
        :root[data-bs-theme="light"] {
            --app-bg: #f4f6f9;
            --card-bg: #ffffff;
            --card-border: rgba(15, 23, 42, 0.10);
        }
        body { background-color: var(--app-bg); transition: background-color 0.2s ease; }
        .admin-card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; }
    </style>
</head>
<body class="pb-5">
    <nav class="border-bottom border-secondary border-opacity-25 bg-body px-3 px-md-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0">
                Vanill-<span class="text-primary">IA</span> <span class="fw-normal text-body-secondary">Chat</span>
                <span class="badge bg-info bg-opacity-25 text-info ms-2 small"><?= htmlspecialchars(I18n::t('admin_badge')) ?></span>
            </h5>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                <button type="button" class="btn <?= $lang === 'fr' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('fr')">FR</button>
                <button type="button" class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchLanguage('en')">EN</button>
            </div>
            <button type="button" id="themeToggleBtn" class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()" title="<?= htmlspecialchars(I18n::t('theme_toggle_title')) ?>">
                <i class="bi <?= $theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?>"></i>
            </button>
            <a href="/api/admin/logs/export" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> <?= htmlspecialchars(I18n::t('admin_export_csv')) ?></a>
            <a href="/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> <?= htmlspecialchars(I18n::t('admin_back_chat')) ?></a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Statistiques 30 jours -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="admin-card p-3 shadow-sm">
                    <div class="text-body-secondary small"><?= htmlspecialchars(I18n::t('admin_kpi_users')) ?></div>
                    <div class="fs-3 fw-bold"><?= (int)$stats['active_users'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-card p-3 shadow-sm">
                    <div class="text-body-secondary small"><?= htmlspecialchars(I18n::t('admin_kpi_requests')) ?></div>
                    <div class="fs-3 fw-bold text-info"><?= number_format((int)$stats['total_requests'], 0, ',', ' ') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-card p-3 shadow-sm">
                    <div class="text-body-secondary small"><?= htmlspecialchars(I18n::t('admin_kpi_tokens')) ?></div>
                    <div class="fs-3 fw-bold text-primary"><?= number_format((int)$stats['total_tokens'], 0, ',', ' ') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-card p-3 shadow-sm">
                    <div class="text-body-secondary small"><?= htmlspecialchars(I18n::t('admin_kpi_budget')) ?></div>
                    <div class="fs-3 fw-bold text-success"><?= number_format(((int)$stats['total_credits_spent']) / 1000000, 2, ',', ' ') ?> EUR</div>
                </div>
            </div>
        </div>

        <!-- 1. Gestion des Utilisateurs, Suivi Consommation & Reset Mensuel -->
        <div class="admin-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i><?= htmlspecialchars(I18n::t('admin_users_title')) ?></h6>
                    <small class="text-body-secondary"><?= htmlspecialchars(I18n::t('admin_users_sub')) ?></small>
                </div>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newUserModal"><i class="bi bi-person-plus me-1"></i> <?= htmlspecialchars(I18n::t('admin_users_new')) ?></button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle small mb-0">
                    <thead>
                        <tr class="text-body-secondary">
                            <th><?= htmlspecialchars(I18n::t('admin_col_user')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_role')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_conso')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_balance')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_limit')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_cycle')) ?></th>
                            <th class="text-center"><?= htmlspecialchars(I18n::t('admin_col_autoreset')) ?></th>
                            <th class="text-center"><?= htmlspecialchars(I18n::t('admin_col_active')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $lastRefillTs = !empty($u['last_refill_at']) ? strtotime($u['last_refill_at']) : time();
                            $nextRefillTs = $lastRefillTs + (30 * 86400);
                            $daysLeft     = max(0, (int)ceil(($nextRefillTs - time()) / 86400));
                            $pctRem       = $u['credits_monthly_limit'] > 0
                                ? min(100, max(0, (int)round(($u['credits_balance'] / $u['credits_monthly_limit']) * 100)))
                                : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1">
                                    <?= htmlspecialchars($u['display_name']) ?>
                                    <?= !empty($u['entra_oid']) ? '<span class="badge bg-primary bg-opacity-25 text-primary ms-1">Entra</span>' : '' ?>
                                </div>
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm bg-body-tertiary u-role" style="width: 95px;">
                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </td>
                            <td>
                                <div class="fw-semibold text-info">
                                    <?= number_format((int)$u['tokens_total_30d'], 0, ',', ' ') ?> tok
                                    <span class="text-body-secondary fw-normal">(<?= (int)$u['requests_30d'] ?> req)</span>
                                </div>
                                <div class="text-success" style="font-size: 0.75rem;">
                                    <?= number_format(((int)$u['credits_spent_30d']) / 1000000, 3, ',', ' ') ?> <?= htmlspecialchars(I18n::t('admin_consumed_eur')) ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="number" step="0.5" class="form-control form-control-sm bg-body-tertiary u-balance" style="width: 95px;" value="<?= round($u['credits_balance'] / 1000000, 2) ?>">
                                    <span class="badge <?= $pctRem > 35 ? 'bg-success' : ($pctRem > 15 ? 'bg-warning' : 'bg-danger') ?> bg-opacity-25 text-body"><?= $pctRem ?>%</span>
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.5" class="form-control form-control-sm bg-body-tertiary u-limit" style="width: 95px;" value="<?= round($u['credits_monthly_limit'] / 1000000, 2) ?>">
                            </td>
                            <td>
                                <?php if ((int)$u['auto_refill'] === 1): ?>
                                    <div class="fw-medium">
                                        <i class="bi bi-calendar-event text-primary me-1"></i><?= date('d/m/Y', $nextRefillTs) ?>
                                        <span class="badge bg-primary bg-opacity-25 text-primary ms-1">J-<?= $daysLeft ?></span>
                                    </div>
                                    <div class="text-body-secondary" style="font-size: 0.72rem;"><?= htmlspecialchars(I18n::t('admin_last_reset')) ?> <?= date('d/m/Y', $lastRefillTs) ?></div>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-body-secondary"><?= htmlspecialchars(I18n::t('admin_reset_disabled')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input u-autorefill" <?= (int)$u['auto_refill'] === 1 ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input u-active" <?= $u['is_active'] ? 'checked' : '' ?>>
                            </td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-info me-1" onclick="openUserUsageModal(<?= (int)$u['id'] ?>)" title="<?= htmlspecialchars(I18n::t('admin_btn_history_title')) ?>">
                                    <i class="bi bi-bar-chart-line me-1"></i><?= htmlspecialchars(I18n::t('admin_btn_history')) ?>
                                </button>
                                <button class="btn btn-sm btn-outline-warning me-1" onclick="resetUserQuotaNow(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['display_name'])) ?>')" title="<?= htmlspecialchars(I18n::t('admin_btn_reset_title')) ?>">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="saveUser(<?= (int)$u['id'] ?>, this)" title="<?= htmlspecialchars(I18n::t('admin_btn_save_title')) ?>">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Historique Global Jour par Jour (30 derniers jours) -->
        <div class="admin-card p-4 mb-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 text-warning me-2"></i><?= htmlspecialchars(I18n::t('admin_daily_title')) ?></h6>
                    <small class="text-body-secondary"><?= htmlspecialchars(I18n::t('admin_daily_sub')) ?></small>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle small mb-0">
                    <thead>
                        <tr class="text-body-secondary">
                            <th><?= htmlspecialchars(I18n::t('col_date')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_daily_active_users')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('col_requests')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('col_tokens_in')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('col_tokens_out')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('col_tokens_total')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_daily_cost')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyGlobalStats)): ?>
                            <tr><td colspan="7" class="text-center text-body-secondary py-3"><?= htmlspecialchars(I18n::t('admin_daily_empty')) ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyGlobalStats as $d): ?>
                            <tr>
                                <td class="fw-semibold"><?= date('d/m/Y', strtotime($d['usage_date'])) ?></td>
                                <td><span class="badge bg-secondary bg-opacity-25 text-body"><?= (int)$d['active_users'] ?></span></td>
                                <td><?= number_format((int)$d['requests_count'], 0, ',', ' ') ?></td>
                                <td class="text-body-secondary"><?= number_format((int)$d['tokens_in'], 0, ',', ' ') ?></td>
                                <td class="text-body-secondary"><?= number_format((int)$d['tokens_out'], 0, ',', ' ') ?></td>
                                <td class="fw-semibold text-primary"><?= number_format((int)$d['tokens_total'], 0, ',', ' ') ?></td>
                                <td class="fw-semibold text-success"><?= number_format(((int)$d['credits_spent']) / 1000000, 4, ',', ' ') ?> EUR</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Gestion des Modèles IA, RBAC & Clés API -->
        <div class="admin-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-cpu text-info me-2"></i><?= htmlspecialchars(I18n::t('admin_models_title')) ?></h6>
                    <small class="text-body-secondary"><?= htmlspecialchars(I18n::t('admin_models_sub')) ?></small>
                </div>
                <button class="btn btn-sm btn-info fw-semibold" data-bs-toggle="modal" data-bs-target="#newModelModal">
                    <i class="bi bi-plus-circle me-1"></i> <?= htmlspecialchars(I18n::t('admin_models_add')) ?>
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr class="text-body-secondary">
                            <th><?= htmlspecialchars(I18n::t('admin_col_display_name')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_provider')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_model_id')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_min_role')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_in_eur')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_out_eur')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_api_key')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_default')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_active')) ?></th>
                            <th><?= htmlspecialchars(I18n::t('admin_col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($models as $m): ?>
                        <tr>
                            <td><input type="text" class="form-control form-control-sm bg-body-tertiary m-name" value="<?= htmlspecialchars($m['display_name']) ?>"></td>
                            <td>
                                <select class="form-select form-select-sm bg-body-tertiary m-provider">
                                    <option value="google_ai" <?= $m['provider'] === 'google_ai' ? 'selected' : '' ?>>Google AI Studio</option>
                                    <option value="vertex_gemini" <?= $m['provider'] === 'vertex_gemini' ? 'selected' : '' ?>>GCP Vertex AI</option>
                                    <option value="anthropic" <?= $m['provider'] === 'anthropic' ? 'selected' : '' ?>>Anthropic</option>
                                    <option value="openai" <?= $m['provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm bg-body-tertiary text-info font-monospace m-id" value="<?= htmlspecialchars($m['model_identifier']) ?>"></td>
                            <td>
                                <select class="form-select form-select-sm bg-body-tertiary m-role" style="width:95px;">
                                    <option value="user" <?= ($m['min_role'] ?? 'user') === 'user' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('admin_role_all')) ?></option>
                                    <option value="admin" <?= ($m['min_role'] ?? 'user') === 'admin' ? 'selected' : '' ?>><?= htmlspecialchars(I18n::t('admin_role_admin')) ?></option>
                                </select>
                            </td>
                            <td><input type="number" step="0.05" class="form-control form-control-sm bg-body-tertiary m-in" style="width: 85px;" value="<?= round($m['cost_input_per_1m'] / 1000000, 2) ?>"></td>
                            <td><input type="number" step="0.05" class="form-control form-control-sm bg-body-tertiary m-out" style="width: 85px;" value="<?= round($m['cost_output_per_1m'] / 1000000, 2) ?>"></td>
                            <td><input type="password" class="form-control form-control-sm bg-body-tertiary m-key" placeholder="<?= htmlspecialchars(!empty($m['config_json']) ? I18n::t('admin_key_saved') : I18n::t('admin_key_env')) ?>"></td>
                            <td class="text-center"><input type="radio" name="default_model" class="form-check-input m-default" <?= $m['is_default'] ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" class="form-check-input m-enabled" <?= $m['is_enabled'] ? 'checked' : '' ?>></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-info me-1" onclick="saveModel(<?= (int)$m['id'] ?>, this)" title="<?= htmlspecialchars(I18n::t('admin_btn_save_title')) ?>"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteModel(<?= (int)$m['id'] ?>)" title="<?= htmlspecialchars(I18n::t('chat_delete')) ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. System Prompt Global & Base de Connaissances RAG -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="admin-card p-4 h-100 d-flex flex-column shadow-sm">
                    <h6 class="fw-bold mb-2"><i class="bi bi-terminal text-warning me-2"></i><?= htmlspecialchars(I18n::t('admin_global_prompt_title')) ?></h6>
                    <p class="text-body-secondary small mb-3"><?= htmlspecialchars(I18n::t('admin_global_prompt_sub')) ?></p>
                    <textarea id="globalPromptInput" class="form-control bg-body-tertiary border-secondary border-opacity-25 flex-grow-1 mb-3 small" rows="6"><?= htmlspecialchars($globalPrompt) ?></textarea>
                    <button class="btn btn-sm btn-warning fw-semibold align-self-end" onclick="saveGlobalPrompt(this)"><?= htmlspecialchars(I18n::t('admin_global_prompt_save')) ?></button>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card p-4 h-100 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0"><i class="bi bi-database-check text-success me-2"></i><?= htmlspecialchars(I18n::t('admin_rag_title')) ?></h6>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newRagModal"><i class="bi bi-upload me-1"></i> <?= htmlspecialchars(I18n::t('admin_rag_add')) ?></button>
                    </div>
                    <p class="text-body-secondary small mb-3"><?= htmlspecialchars(I18n::t('admin_rag_sub')) ?></p>
                    <div class="list-group list-group-flush gap-2" style="max-height: 210px; overflow-y: auto;">
                        <?php if (empty($knowledgeDocs)): ?>
                            <div class="text-body-secondary small text-center py-4"><?= htmlspecialchars(I18n::t('admin_rag_empty')) ?></div>
                        <?php endif; ?>
                        <?php foreach ($knowledgeDocs as $doc): ?>
                            <div class="list-group-item bg-body-tertiary border border-secondary border-opacity-25 rounded d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($doc['title']) ?></div>
                                    <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars($doc['original_name']) ?> - <?= number_format((int)$doc['char_len'], 0, ',', ' ') ?> <?= htmlspecialchars(I18n::t('admin_rag_chars')) ?></div>
                                </div>
                                <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteKnowledge(<?= (int)$doc['id'] ?>)"><i class="bi bi-trash"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Détails Consommation Utilisateur -->
    <div class="modal fade" id="userUsageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><i class="bi bi-bar-chart-line text-info me-2"></i><?= htmlspecialchars(I18n::t('admin_um_title')) ?> <span id="umUserName" class="text-info"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="p-2 rounded bg-body-tertiary border border-secondary border-opacity-25">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('admin_um_balance')) ?></div>
                                <div class="fw-bold mt-1"><span id="umBalance"></span> / <span id="umLimit"></span> EUR</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 rounded bg-body-tertiary border border-secondary border-opacity-25">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('admin_um_last_reset')) ?></div>
                                <div class="fw-bold mt-1" id="umLastReset"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 rounded bg-body-tertiary border border-secondary border-opacity-25">
                                <div class="text-body-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars(I18n::t('admin_um_next_reset')) ?></div>
                                <div class="fw-bold text-primary mt-1" id="umNextReset"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-cpu me-1 text-info"></i><?= htmlspecialchars(I18n::t('admin_um_by_model')) ?></h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th><?= htmlspecialchars(I18n::t('col_model')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_requests')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_in')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_out')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_tokens_total')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_cost_eur')) ?></th>
                                </tr>
                            </thead>
                            <tbody id="umModelsBody"></tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-calendar3 me-1 text-warning"></i><?= htmlspecialchars(I18n::t('admin_um_daily')) ?></h6>
                    <div class="table-responsive mb-3" style="max-height: 220px;">
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
                            <tbody id="umDailyBody"></tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1 text-body-secondary"></i><?= htmlspecialchars(I18n::t('admin_um_recent')) ?></h6>
                    <div class="table-responsive" style="max-height: 200px;">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th><?= htmlspecialchars(I18n::t('admin_um_recent_date')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_model')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('admin_um_recent_tokens')) ?></th>
                                    <th><?= htmlspecialchars(I18n::t('col_cost_eur')) ?></th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody id="umRecentBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Ajout Modèle IA -->
    <div class="modal fade" id="newModelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><?= htmlspecialchars(I18n::t('admin_nm_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_name')) ?></label>
                        <input type="text" id="nmName" class="form-control form-control-sm bg-body-tertiary" placeholder="Ex: Gemini 2.5 Flash">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_provider')) ?></label>
                            <select id="nmProvider" class="form-select form-select-sm bg-body-tertiary">
                                <option value="google_ai">Google AI Studio (API Key)</option>
                                <option value="vertex_gemini">Google Cloud Vertex AI</option>
                                <option value="anthropic">Anthropic (Claude)</option>
                                <option value="openai">OpenAI (ChatGPT)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_role')) ?></label>
                            <select id="nmRole" class="form-select form-select-sm bg-body-tertiary">
                                <option value="user"><?= htmlspecialchars(I18n::t('admin_nm_role_all')) ?></option>
                                <option value="admin"><?= htmlspecialchars(I18n::t('admin_nm_role_admin')) ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_id')) ?></label>
                        <input type="text" id="nmId" class="form-control form-control-sm bg-body-tertiary font-monospace" placeholder="Ex: gemini-2.5-flash / gpt-4o">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_in')) ?></label>
                            <input type="number" step="0.05" id="nmIn" value="0.15" class="form-control form-control-sm bg-body-tertiary">
                        </div>
                        <div class="col-6">
                            <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_out')) ?></label>
                            <input type="number" step="0.05" id="nmOut" value="0.60" class="form-control form-control-sm bg-body-tertiary">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nm_key')) ?></label>
                        <input type="password" id="nmKey" class="form-control form-control-sm bg-body-tertiary" placeholder="AIzaSy... / sk-...">
                    </div>
                    <button class="btn btn-info btn-sm w-100 fw-semibold" onclick="createModel()"><?= htmlspecialchars(I18n::t('admin_nm_submit')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Ajout Document RAG -->
    <div class="modal fade" id="newRagModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><?= htmlspecialchars(I18n::t('admin_nrag_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nrag_doc_title')) ?></label>
                        <input type="text" id="ragTitle" class="form-control form-control-sm bg-body-tertiary" placeholder="Ex: Onboarding & IT Support SOP">
                    </div>
                    <div class="mb-2">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nrag_file')) ?></label>
                        <input type="file" id="ragFile" class="form-control form-control-sm bg-body-tertiary">
                    </div>
                    <div class="text-center text-body-secondary small my-2"><?= htmlspecialchars(I18n::t('admin_nrag_or')) ?></div>
                    <div class="mb-3">
                        <label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nrag_text')) ?></label>
                        <textarea id="ragText" class="form-control form-control-sm bg-body-tertiary" rows="4"></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100 fw-semibold" onclick="uploadKnowledge()"><?= htmlspecialchars(I18n::t('admin_nrag_submit')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Création Utilisateur -->
    <div class="modal fade" id="newUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-secondary border-opacity-25">
                <div class="modal-header border-secondary border-opacity-25">
                    <h6 class="modal-title fw-bold"><?= htmlspecialchars(I18n::t('admin_nu_title')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nu_name')) ?></label><input type="text" id="nuName" class="form-control form-control-sm bg-body-tertiary"></div>
                    <div class="mb-2"><label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nu_email')) ?></label><input type="email" id="nuEmail" class="form-control form-control-sm bg-body-tertiary"></div>
                    <div class="mb-2"><label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nu_pass')) ?></label><input type="password" id="nuPass" class="form-control form-control-sm bg-body-tertiary"></div>
                    <div class="mb-3"><label class="small text-body-secondary"><?= htmlspecialchars(I18n::t('admin_nu_limit')) ?></label><input type="number" id="nuLimit" value="5" class="form-control form-control-sm bg-body-tertiary"></div>
                    <button class="btn btn-primary btn-sm w-100" onclick="createUser()"><?= htmlspecialchars(I18n::t('admin_nu_submit')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = "<?= htmlspecialchars($csrfToken) ?>";
        const I18N = <?= json_encode(I18n::all(), JSON_UNESCAPED_UNICODE) ?>;
        const CURRENT_LANG = <?= json_encode($lang) ?>;
        const LOCALE = CURRENT_LANG === 'en' ? 'en-US' : 'fr-FR';

        function syncThemeIcon(theme) {
            const icon = document.querySelector('#themeToggleBtn i');
            if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        syncThemeIcon(document.documentElement.getAttribute('data-bs-theme') || 'dark');

        async function toggleTheme() {
            const current = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('vanillia_theme', next);
            document.cookie = `vanillia_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
            syncThemeIcon(next);
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

        async function saveUser(userId, btn) {
            const row = btn.closest('tr');
            const payload = {
                csrf_token: csrfToken,
                action: 'update',
                user_id: userId,
                role: row.querySelector('.u-role').value,
                balance_eur: row.querySelector('.u-balance').value,
                limit_eur: row.querySelector('.u-limit').value,
                auto_refill: row.querySelector('.u-autorefill').checked ? 1 : 0,
                is_active: row.querySelector('.u-active').checked ? 1 : 0
            };
            const res = await fetch('/api/admin/user', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            if (res.ok) {
                btn.innerHTML = 'OK';
                setTimeout(() => location.reload(), 600);
            }
        }

        async function resetUserQuotaNow(userId, name) {
            if (!confirm(I18N.admin_confirm_reset.replace('{name}', name))) return;
            const res = await fetch('/api/admin/user', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'reset_quota', user_id: userId })
            });
            if (res.ok) location.reload();
        }

        async function openUserUsageModal(userId) {
            const modal = new bootstrap.Modal(document.getElementById('userUsageModal'));
            modal.show();

            document.getElementById('umUserName').textContent = I18N.modal_usage_loading;
            document.getElementById('umModelsBody').innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_loading)}</td></tr>`;
            document.getElementById('umDailyBody').innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_loading)}</td></tr>`;
            document.getElementById('umRecentBody').innerHTML = `<tr><td colspan="5" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_loading)}</td></tr>`;

            const res = await fetch(`/api/admin/users/${userId}/usage`);
            const data = await res.json();
            if (!res.ok) return;

            document.getElementById('umUserName').textContent = `${data.user.display_name} (${data.user.email})`;
            document.getElementById('umBalance').textContent = data.user.balance_eur;
            document.getElementById('umLimit').textContent = data.user.limit_eur;
            document.getElementById('umLastReset').textContent = data.user.last_refill_at;
            document.getElementById('umNextReset').textContent = data.user.days_until_reset !== null
                ? `${data.user.next_refill_at} (J-${data.user.days_until_reset})`
                : I18N.admin_um_disabled;

            const mBody = document.getElementById('umModelsBody');
            if (!data.by_model || data.by_model.length === 0) {
                mBody.innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_none_30d)}</td></tr>`;
            } else {
                mBody.innerHTML = data.by_model.map(m => `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(m.model_name)}</td>
                        <td>${m.requests_count}</td>
                        <td class="text-body-secondary">${Number(m.tokens_in).toLocaleString(LOCALE)}</td>
                        <td class="text-body-secondary">${Number(m.tokens_out).toLocaleString(LOCALE)}</td>
                        <td class="fw-semibold text-info">${Number(m.tokens_total).toLocaleString(LOCALE)}</td>
                        <td class="fw-semibold text-success">${m.cost_eur} EUR</td>
                    </tr>
                `).join('');
            }

            const dBody = document.getElementById('umDailyBody');
            if (!data.daily || data.daily.length === 0) {
                dBody.innerHTML = `<tr><td colspan="6" class="text-center text-body-secondary">${escapeHtml(I18N.modal_usage_none_activity)}</td></tr>`;
            } else {
                dBody.innerHTML = data.daily.map(d => `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(d.usage_date)}</td>
                        <td>${d.requests_count}</td>
                        <td class="text-body-secondary">${Number(d.tokens_in).toLocaleString(LOCALE)}</td>
                        <td class="text-body-secondary">${Number(d.tokens_out).toLocaleString(LOCALE)}</td>
                        <td class="fw-semibold text-primary">${Number(d.tokens_total).toLocaleString(LOCALE)}</td>
                        <td class="fw-semibold text-success">${d.cost_eur} EUR</td>
                    </tr>
                `).join('');
            }

            const rBody = document.getElementById('umRecentBody');
            if (!data.recent_logs || data.recent_logs.length === 0) {
                rBody.innerHTML = `<tr><td colspan="5" class="text-center text-body-secondary">${escapeHtml(I18N.admin_um_recent_empty)}</td></tr>`;
            } else {
                rBody.innerHTML = data.recent_logs.map(r => `
                    <tr>
                        <td class="text-body-secondary">${escapeHtml(r.created_at)}</td>
                        <td>${escapeHtml(r.model_name)}</td>
                        <td>${Number(r.tokens_input).toLocaleString(LOCALE)} / ${Number(r.tokens_output).toLocaleString(LOCALE)}</td>
                        <td class="text-success">${r.cost_eur} EUR</td>
                        <td class="text-body-secondary font-monospace">${escapeHtml(r.ip_address || '-')}</td>
                    </tr>
                `).join('');
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        async function createUser() {
            const payload = {
                csrf_token: csrfToken,
                action: 'create',
                display_name: document.getElementById('nuName').value,
                email: document.getElementById('nuEmail').value,
                password: document.getElementById('nuPass').value,
                monthly_eur: document.getElementById('nuLimit').value
            };
            const res = await fetch('/api/admin/user', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            if (res.ok) location.reload();
        }

        async function saveModel(modelId, btn) {
            const row = btn.closest('tr');
            const payload = {
                csrf_token: csrfToken,
                action: 'update',
                model_id: modelId,
                display_name: row.querySelector('.m-name').value,
                provider: row.querySelector('.m-provider').value,
                model_identifier: row.querySelector('.m-id').value,
                min_role: row.querySelector('.m-role').value,
                cost_in_eur: row.querySelector('.m-in').value,
                cost_out_eur: row.querySelector('.m-out').value,
                api_key: row.querySelector('.m-key').value,
                is_default: row.querySelector('.m-default').checked ? 1 : 0,
                is_enabled: row.querySelector('.m-enabled').checked ? 1 : 0
            };
            const res = await fetch('/api/admin/model', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            if (res.ok) {
                btn.innerHTML = 'OK';
                row.querySelector('.m-key').value = '';
                setTimeout(() => btn.innerHTML = '<i class="bi bi-check-lg"></i>', 1500);
            }
        }

        async function createModel() {
            const payload = {
                csrf_token: csrfToken,
                action: 'create',
                display_name: document.getElementById('nmName').value,
                provider: document.getElementById('nmProvider').value,
                min_role: document.getElementById('nmRole').value,
                model_identifier: document.getElementById('nmId').value,
                cost_in_eur: document.getElementById('nmIn').value,
                cost_out_eur: document.getElementById('nmOut').value,
                api_key: document.getElementById('nmKey').value
            };
            const res = await fetch('/api/admin/model', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            if (res.ok) location.reload();
        }

        async function deleteModel(modelId) {
            if (!confirm(I18N.admin_confirm_delete_model)) return;
            const res = await fetch('/api/admin/model', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'delete', model_id: modelId }) });
            if (res.ok) location.reload();
        }

        async function saveGlobalPrompt(btn) {
            const val = document.getElementById('globalPromptInput').value;
            const res = await fetch('/api/admin/settings', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, global_system_prompt: val }) });
            if (res.ok) {
                btn.textContent = I18N.admin_global_prompt_saved;
                setTimeout(() => btn.textContent = I18N.admin_global_prompt_save, 1800);
            }
        }

        async function uploadKnowledge() {
            const fd = new FormData();
            fd.append('title', document.getElementById('ragTitle').value);
            fd.append('content_text', document.getElementById('ragText').value);
            const fileInput = document.getElementById('ragFile');
            if (fileInput.files.length > 0) {
                fd.append('file', fileInput.files[0]);
            }
            const res = await fetch('/api/admin/knowledge', { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok) {
                alert(data.error || 'RAG Error');
                return;
            }
            location.reload();
        }

        async function deleteKnowledge(id) {
            if (!confirm(I18N.admin_rag_confirm_delete)) return;
            await fetch('/api/admin/knowledge/' + id + '/delete', { method: 'POST' });
            location.reload();
        }
    </script>
</body>
</html>
