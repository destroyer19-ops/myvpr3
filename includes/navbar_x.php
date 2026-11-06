<?php
require_once __DIR__ . '/db.php';

$current_page = basename($_SERVER['PHP_SELF']);
$has_active_crusade = isset($_SESSION['current_crusade_code']);
$has_active_meeting = isset($_SESSION['current_meeting_code']);

$is_subscribed = false;
if (isset($_SESSION['user_id'])) {
    global $conn;
    $user_id = $_SESSION['user_id'];
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $subscription = $result->fetch_assoc();
            $stmt->close();

            if ($subscription) {
                $end_date = new DateTime($subscription['end_date']);
                $now = new DateTime();
                if ($end_date > $now) {
                    $is_subscribed = true;
                }
            }
        }
    }
}
?>

<!-- X-style Navbar (test) -->
<nav class="navx" aria-label="Primary">
    <div class="navx__inner">
        <div class="navx__left">
            <a class="navx__brand" href="index.php" aria-label="Virtual Praise Room Home">
                <img src="logo.png" alt="Virtual Praise Room Logo">
            </a>
            <?php if ($has_active_crusade) : ?>
                <a class="navx__badge" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">
                    Resume Crusade
                </a>
            <?php endif; ?>
        </div>

        <div class="navx__center" role="navigation" aria-label="Main">
            <?php if (isset($_SESSION['user_id'])) : ?>
                <a class="navx__link <?php echo ($current_page == 'dashboard.php') ? 'is-active' : ''; ?>" href="dashboard.php">Dashboard</a>
                <div class="navx__dropdown">
                    <button class="navx__link navx__link--toggle" type="button" data-navx-toggle="watch" aria-expanded="false">
                        Watch
                    </button>
                    <div class="navx__menu" data-navx-menu="watch" role="menu">
                        <a class="navx__menu-item" href="live_tv.php" role="menuitem">Live TV</a>
                        <a class="navx__menu-item" href="videos.php" role="menuitem">Videos</a>
                    </div>
                </div>
                <div class="navx__dropdown">
                    <button class="navx__link navx__link--toggle" type="button" data-navx-toggle="create" aria-expanded="false">
                        Create
                    </button>
                    <div class="navx__menu" data-navx-menu="create" role="menu">
                        <a class="navx__menu-item" href="create_crusade.php" role="menuitem">Create Crusade</a>
                        <a class="navx__menu-item" href="create_meeting.php" role="menuitem">Create Worship Cloud</a>
                        <a class="navx__menu-item" href="create_praise_session.php" role="menuitem">Create Meeting</a>
                        <a class="navx__menu-item" href="share_testimony.php" role="menuitem">Share Testimony</a>
                    </div>
                </div>
                <div class="navx__dropdown">
                    <button class="navx__link navx__link--toggle" type="button" data-navx-toggle="account" aria-expanded="false">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </button>
                    <div class="navx__menu" data-navx-menu="account" role="menu">
                        <?php if (isset($_SESSION['current_meeting_code'])) : ?>
                            <a class="navx__menu-item" href="meeting-room.php?code=<?php echo htmlspecialchars($_SESSION['current_meeting_code']); ?>" role="menuitem">My Current Meeting</a>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['current_crusade_code'])) : ?>
                            <a class="navx__menu-item" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>" role="menuitem">My Crusade</a>
                        <?php endif; ?>
                        <a class="navx__menu-item" href="quarterly_wrap.php" role="menuitem">Quarterly Wrap</a>
                        <div class="navx__menu-divider"></div>
                        <a class="navx__menu-item" href="logout.php" role="menuitem">Logout</a>
                    </div>
                </div>
            <?php endif; ?>
            <div class="navx__translate">
                <div class="gtranslate_wrapper"></div>
            </div>
        </div>

        <div class="navx__right">
            <div class="navx__actions">
                <button class="navx__cta navx__cta--ghost" type="button" data-bs-toggle="modal" data-bs-target="#giveLifeModal">
                    Salvation
                </button>
                <button class="navx__cta" type="button" data-bs-toggle="modal" data-bs-target="#givingModal">
                    Give
                </button>
                <?php if (!$is_subscribed && isset($_SESSION['user_id'])) : ?>
                    <a class="navx__cta navx__cta--accent" href="subscribe.php">Subscribe</a>
                <?php endif; ?>
                <?php if (!isset($_SESSION['user_id'])) : ?>
                    <a class="navx__cta navx__cta--accent" href="register.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>

        <button class="navx__mobile-toggle" type="button" aria-expanded="false" aria-controls="navxMobile">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<div class="navx__mobile" id="navxMobile" aria-hidden="true">
    <div class="navx__mobile-head">
        <a class="navx__brand" href="index.php">
            <img src="logo.png" alt="Virtual Praise Room Logo">
        </a>
        <button class="navx__mobile-close" type="button" aria-label="Close menu">&times;</button>
    </div>

    <div class="navx__mobile-body">
        <?php if (isset($_SESSION['user_id'])) : ?>
            <a class="navx__mobile-link" href="dashboard.php">Dashboard</a>

            <button class="navx__mobile-link navx__mobile-toggle" type="button" data-navx-mobile-toggle="watch">Watch</button>
            <div class="navx__mobile-sub" data-navx-mobile="watch">
                <a href="live_tv.php">Live TV</a>
                <a href="videos.php">Videos</a>
            </div>

            <button class="navx__mobile-link navx__mobile-toggle" type="button" data-navx-mobile-toggle="create">Create</button>
            <div class="navx__mobile-sub" data-navx-mobile="create">
                <a href="create_crusade.php">Create Crusade</a>
                <a href="create_meeting.php">Create Worship Cloud</a>
                <a href="create_praise_session.php">Create Meeting</a>
                <a href="share_testimony.php">Share Testimony</a>
            </div>

            <button class="navx__mobile-link navx__mobile-toggle" type="button" data-navx-mobile-toggle="account">Account</button>
            <div class="navx__mobile-sub" data-navx-mobile="account">
                <?php if (isset($_SESSION['current_meeting_code'])) : ?>
                    <a href="meeting-room.php?code=<?php echo htmlspecialchars($_SESSION['current_meeting_code']); ?>">My Current Meeting</a>
                <?php endif; ?>
                <?php if (isset($_SESSION['current_crusade_code'])) : ?>
                    <a href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">My Crusade</a>
                <?php endif; ?>
                <a href="quarterly_wrap.php">Quarterly Wrap</a>
                <a href="logout.php">Logout</a>
            </div>
        <?php endif; ?>

        <div class="navx__mobile-translate">
            <div class="gtranslate_wrapper"></div>
        </div>

        <?php if ($has_active_crusade) : ?>
            <a class="navx__mobile-badge" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">Resume Crusade</a>
        <?php endif; ?>
        <?php if ($has_active_meeting) : ?>
            <a class="navx__mobile-badge" href="meeting-room.php?code=<?php echo htmlspecialchars($_SESSION['current_meeting_code']); ?>">Resume Meeting</a>
        <?php endif; ?>

        <?php if (!$is_subscribed && isset($_SESSION['user_id'])) : ?>
            <a class="navx__mobile-cta" href="subscribe.php">Subscribe</a>
        <?php endif; ?>
        <?php if (!isset($_SESSION['user_id'])) : ?>
            <a class="navx__mobile-cta" href="register.php">Sign Up</a>
        <?php endif; ?>

        <div class="navx__mobile-section">
            <button class="navx__mobile-action" type="button" data-bs-toggle="modal" data-bs-target="#giveLifeModal">Salvation</button>
            <button class="navx__mobile-action" type="button" data-bs-toggle="modal" data-bs-target="#givingModal">Give</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var root = document.documentElement;
        var toggle = document.querySelector('.navx__mobile-toggle');
        var drawer = document.getElementById('navxMobile');
        var closeBtn = document.querySelector('.navx__mobile-close');

        if (toggle && drawer) {
            var openDrawer = function () {
                drawer.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
                drawer.setAttribute('aria-hidden', 'false');
                root.classList.add('navx-lock');
            };
            var closeDrawer = function () {
                drawer.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                drawer.setAttribute('aria-hidden', 'true');
                root.classList.remove('navx-lock');
            };

            toggle.addEventListener('click', function () {
                if (drawer.classList.contains('is-open')) {
                    closeDrawer();
                } else {
                    openDrawer();
                }
            });
            if (closeBtn) {
                closeBtn.addEventListener('click', closeDrawer);
            }
            drawer.addEventListener('click', function (event) {
                if (event.target === drawer) {
                    closeDrawer();
                }
            });
        }

        document.querySelectorAll('[data-navx-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-navx-toggle');
                var menu = document.querySelector('[data-navx-menu="' + key + '"]');
                if (!menu) {
                    return;
                }
                var isOpen = menu.classList.contains('is-open');
                document.querySelectorAll('.navx__menu.is-open').forEach(function (openMenu) {
                    if (openMenu !== menu) {
                        openMenu.classList.remove('is-open');
                    }
                });
                menu.classList.toggle('is-open', !isOpen);
                btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
        });

        document.addEventListener('click', function (event) {
            if (event.target.closest('.navx__dropdown')) {
                return;
            }
            document.querySelectorAll('.navx__menu.is-open').forEach(function (menu) {
                menu.classList.remove('is-open');
            });
            document.querySelectorAll('[data-navx-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
        });

        document.querySelectorAll('[data-navx-mobile-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-navx-mobile-toggle');
                var panel = document.querySelector('[data-navx-mobile="' + key + '"]');
                if (!panel) {
                    return;
                }
                panel.classList.toggle('is-open');
            });
        });
    })();
</script>
