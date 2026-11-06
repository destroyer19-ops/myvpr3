    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js?v=<?php echo time(); ?>"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js?v=<?php echo time(); ?>"></script>
    <script>
        AOS.init();
    </script>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('./sw.js')
            .then(registration => {
              console.log('ServiceWorker registration successful with scope: ', registration.scope);
            }, err => {
              console.log('ServiceWorker registration failed: ', err);
            });
        });
      }
    </script>
    <script>
        let deferredPrompt;
        const installButton = document.getElementById('install-button');
        console.log('Install button element found:', installButton); // Diagnostic log

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installButton) { // Ensure button exists before trying to show it
                installButton.style.display = 'block';
            }
            console.log('beforeinstallprompt fired. deferredPrompt:', deferredPrompt);
        });

        if (installButton) {
            installButton.addEventListener('click', (e) => {
                console.log('Install button clicked. deferredPrompt:', deferredPrompt);
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        } else {
                            console.log('User dismissed the install prompt');
                        }
                        deferredPrompt = null;
                    });
                } else {
                    console.log('deferredPrompt is null. The beforeinstallprompt event might not have fired or was already handled.');
                }
            });
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
            const navbar = document.querySelector('.navbar');
            if (navbar) { // Check if navbar exists
                window.addEventListener('scroll', function () {
                    if (window.scrollY > 50) {
                        navbar.classList.add('navbar-scrolled');
                    } else {
                        navbar.classList.remove('navbar-scrolled');
                    }
                });
            }

            <?php endif; ?>
        });
    </script>
    <!-- Include Giving Modal -->
    <?php include 'giving_modal.php'; ?>
    <!-- Include Give Life Modal -->
    <?php include 'give_life_modal.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var giveLifeModal = document.getElementById('giveLifeModal');
            if(giveLifeModal) {
                giveLifeModal.addEventListener('show.bs.modal', function (event) {
                    // Button that triggered the modal
                    var button = event.relatedTarget;
                    // Extract info from data-bs-* attributes
                    var crusadeCode = button.getAttribute('data-crusade-code');
                    
                    var logDecisionButton = giveLifeModal.querySelector('#logDecisionButton');
                    var originalHref = 'log_salvation_decision.php';
                    
                    if (crusadeCode) {
                        logDecisionButton.href = originalHref + '?crusade_code=' + crusadeCode;
                    } else {
                        logDecisionButton.href = originalHref;
                    }
                });
            }
        });
    </script>
    
    <!-- GTranslate dropdown widget -->
    <script>
        window.gtranslateSettings = {
            default_language: 'en',
            wrapper_selector: '.gtranslate_wrapper',
            select_language_label: 'Translate'
        };
    </script>
    <script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js" defer></script>
</body>
</html>
