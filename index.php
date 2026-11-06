<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('session.use_only_cookies', 1);
// index.php
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}



require_once 'includes/db.php';

// Fetch the latest live stream for the cinematic hero background
$hero_stream = null;
$stmt_hero = $conn->prepare("SELECT * FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC LIMIT 1");
if ($stmt_hero) {
    $stmt_hero->execute();
    $hero_stream = $stmt_hero->get_result()->fetch_assoc();
    $stmt_hero->close();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? $_SESSION['username'] : 'Guest';
$current_crusade_code = $_SESSION['current_crusade_code'] ?? '';
$current_meeting_code = $_SESSION['current_meeting_code'] ?? '';
?>
<style>
    body {
        padding-top: 0 !important;
    }
    .navbar {
        background: transparent !important;
        box-shadow: none !important;
        transition: all 0.4s ease-in-out;
        padding: 20px 0;
    }
    .navbar.navbar-scrolled {
        background: rgba(2, 12, 27, 0.95) !important;
        backdrop-filter: blur(10px);
        padding: 10px 0;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3) !important;
    }
    .navbar .nav-link, .navbar .navbar-brand {
        color: #fff !important;
    }
    .navbar.navbar-scrolled .nav-link, .navbar.navbar-scrolled .navbar-brand {
        color: #fff !important;
    }
    .hero-section {            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            background: #000;
            padding: 0 !important;
        }
        .hero-video-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            object-fit: cover;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(to right, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.3) 50%, transparent 100%),
                linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.2) 20%, transparent 50%);
            z-index: 2;
        }
        .hero-content-wrapper {
            position: relative;
            z-index: 3;
            color: #fff;
            width: 100%;
        }
        .hero-content {
            text-align: left !important;
            padding-left: 0;
        }
        .hero-content h1 {
            color: #fff !important;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        .hero-content p {
            color: rgba(255,255,255,0.8) !important;
            max-width: 600px;
        }
        .live-indicator {
            display: inline-flex;
            align-items: center;
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff4d5e;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #ff4d5e;
            border-radius: 50%;
            margin-right: 8px;
            box-shadow: 0 0 10px #ff4d5e;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }
        @media (max-width: 991px) {
            .hero-section {
                min-height: 80vh;
                padding-top: 80px !important;
            }
            .hero-overlay {
                background: 
                    linear-gradient(to bottom, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.9) 100%),
                    linear-gradient(to right, rgba(0,0,0,0.7) 0%, transparent 100%);
            }
            .hero-content {
                text-align: center !important;
                margin: 0 auto;
                padding: 0 15px;
            }
            .hero-content h1 {
                font-size: 2.5rem !important;
            }
            .hero-content p {
                font-size: 1rem !important;
                margin-left: auto;
                margin-right: auto;
            }
            .live-indicator {
                font-size: 0.7rem;
                padding: 3px 10px;
            }
            .d-flex.flex-wrap.gap-3 {
                justify-content: center;
            }
            .navbar {
                padding: 10px 0;
                background: rgba(0,0,0,0.5) !important;
            }
        }
        @media (max-width: 576px) {
            .hero-section {
                min-height: 70vh;
            }
            .hero-content h1 {
                font-size: 2rem !important;
            }
            .btn-lg {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
<?php include 'includes/header.php'; ?>
<!-- HLS.js for .m3u8 live feeds -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<body>
    <?php include 'includes/navbar.php'; ?>
    <!-- Hero Section -->
    <section class="hero-section" id="hero-section">
        <?php if ($hero_stream): ?>
            <!-- Video Background -->
            <?php 
                $video_url = $hero_stream['stream_url'];
                $is_youtube = (strpos($video_url, 'youtube.com/watch') !== false || strpos($video_url, 'youtu.be/') !== false);
                if ($is_youtube) {
                    $video_id = '';
                    if (strpos($video_url, 'youtube.com/watch') !== false) {
                        $query = parse_url($video_url, PHP_URL_QUERY);
                        parse_str($query ?? '', $params);
                        $video_id = $params['v'] ?? '';
                    } else {
                        $video_id = ltrim(parse_url($video_url, PHP_URL_PATH), '/');
                    }
                    $embed_url = "https://www.youtube.com/embed/{$video_id}?autoplay=1&mute=1&controls=0&loop=1&playlist={$video_id}&rel=0&showinfo=0";
                }
            ?>
            <?php if ($is_youtube): ?>
                <iframe class="hero-video-bg" src="<?php echo $embed_url; ?>" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="pointer-events: none;"></iframe>
            <?php else: ?>
                <video class="hero-video-bg" autoplay muted loop playsinline id="heroVideo">
                    <source src="<?php echo htmlspecialchars($video_url); ?>" type="application/x-mpegURL">
                </video>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var video = document.getElementById('heroVideo');
                        if (!video) return;
                        var videoSrc = "<?php echo $video_url; ?>";
                        if (Hls.isSupported()) {
                            var hls = new Hls();
                            hls.loadSource(videoSrc);
                            hls.attachMedia(video);
                            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                                video.play().catch(function(error) {
                                    console.log("Autoplay blocked or failed:", error);
                                });
                            });
                        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                            video.src = videoSrc;
                            video.addEventListener('loadedmetadata', function() {
                                video.play().catch(function(error) {
                                    console.log("Autoplay blocked or failed:", error);
                                });
                            });
                        }
                    });
                </script>
            <?php endif; ?>
        <?php else: ?>
            <img src="assets/img/bg34.jpg" class="hero-video-bg" alt="Hero Background">
        <?php endif; ?>

        <div class="hero-overlay"></div>

        <div class="container hero-content-wrapper">
            <div class="row">
                <div class="col-lg-7">
                    <div class="hero-content">
                        <?php if ($hero_stream): ?>
                            <div class="live-indicator" data-aos="fade-down">
                                <span class="live-dot"></span>
                                Currently Broadcasting: <?php echo htmlspecialchars($hero_stream['title']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h1 class="display-3 fw-bold mb-4" data-aos="fade-up" data-aos-duration="1000">Praise, Worship, and Connection.</h1>
                        <p class="lead mb-5" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="200">Experience the joy of fellowship and connect with your community in a virtual space designed for praise.</p>
                        
                        <div class="d-flex flex-wrap gap-3" data-aos="fade-up" data-aos-duration="1400" data-aos-delay="400">
                            <?php if ($is_logged_in): ?>
                                <a href="create_meeting.php" class="btn btn-primary-custom btn-lg cta-button-test">Create Meeting</a>
                                <a href="live_tv.php" class="btn btn-outline-light btn-lg">Watch Live TV</a>
                            <?php else: ?>
                                <a href="register.php" class="btn btn-primary-custom btn-lg cta-button-test">Join the Community</a>
                                <a href="login.php" class="btn btn-outline-light btn-lg">Sign In</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($is_logged_in): ?>
    <section class="quick-actions-section py-4">
        <div class="container">
            <div class="row g-3">
                <?php if (!empty($current_crusade_code)): ?>
                <div class="col-md-6">
                    <div class="card p-4 text-center">
                        <h5 class="mb-2">Resume Your Crusade</h5>
                        <p class="text-muted mb-3">Jump back into your live crusade room.</p>
                        <a href="crusade_room.php?code=<?php echo htmlspecialchars($current_crusade_code); ?>" class="btn btn-primary">Resume Crusade</a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($current_meeting_code)): ?>
                <div class="col-md-6">
                    <div class="card p-4 text-center">
                        <h5 class="mb-2">Resume Your Meeting</h5>
                        <p class="text-muted mb-3">Continue where you left off.</p>
                        <a href="meeting-room.php?code=<?php echo htmlspecialchars($current_meeting_code); ?>" class="btn btn-outline-primary">Resume Meeting</a>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <h5 class="mb-2">Create Crusade</h5>
                        <p class="text-muted mb-3">Start a new praise event.</p>
                        <a href="create_crusade.php" class="btn btn-primary">Create</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <h5 class="mb-2">Live TV</h5>
                        <p class="text-muted mb-3">Watch the official stream.</p>
                        <a href="live_tv.php" class="btn btn-outline-primary">Watch</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <h5 class="mb-2">Video Library</h5>
                        <p class="text-muted mb-3">Browse curated videos.</p>
                        <a href="videos.php" class="btn btn-outline-primary">Browse</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Why Choose Virtual Praise Room?</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-people-group"></i></div>
                        <h3>Community Focused</h3>
                        <p>Our platform is meticulously designed to foster a profound sense of community and connection among users through collective worship.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-church"></i></div>
                        <h3>Dedicated Praise Space</h3>
                        <p>Experience an environment crafted specifically for praise and worship, with unique features to enhance your spiritual journey.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3>Secure & Private</h3>
                        <p>Your spiritual gatherings are kept private and secure with state-of-the-art encryption, ensuring your moments of worship remain intimate.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Combined CTA Section -->
    <section class="cta-combined-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0" data-aos="fade-right">
                    <div class="cta-block p-5 text-center rounded shadow-lg h-100">
                        <h3 class="mb-3">Start an Unending Praise Crusade</h3>
                        <p class="mb-4">Create your own virtual praise room and invite people to join you in praise and worship.</p>
                        <a href="crusade.php" class="btn btn-primary-custom btn-custom cta-button-test">Start a Crusade</a>
                    </div>
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <div class="cta-block p-5 text-center rounded shadow-lg h-100">
                        <h3 class="mb-3">Watch The Official Live Crusade</h3>
                        <p class="mb-4">Join our main live event, set by the admin. Watch and share with your community!</p>
                        <a href="live_crusade.php" class="btn btn-primary-custom btn-custom cta-button-test">Watch The Official Crusade</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Promo Cards Section -->
    <section class="promo-cards-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Explore Our Features</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="100">
                    <div class="card promo-card">
                        <div class="card-img-top promo-image-placeholder"></div>
                        <div class="card-body text-center">
                            <h5 class="card-title">Seamless Virtual Meetings</h5>
                            <p class="card-text">Host and join praise sessions with crystal-clear audio and video. Connect globally!</p>
                            <a href="create_meeting.php" class="btn btn-primary-custom btn-sm cta-button-test">Learn More</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="200">
                    <div class="card promo-card">
                        <div class="card-img-top promo-image-placeholder promo-image-alt"></div>
                        <div class="card-body text-center">
                            <h5 class="card-title">Join Live Crusades</h5>
                            <p class="card-text">Participate in official live crusades and connect with a larger community of faith.</p>
                            <a href="live_crusade.php" class="btn btn-secondary-custom btn-sm cta-button-test">Watch Now</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="300">
                    <div class="card promo-card">
                        <div class="card-img-top promo-image-placeholder promo-image-warm"></div>
                        <div class="card-body text-center">
                            <h5 class="card-title">Share Your Testimony</h5>
                            <p class="card-text">Inspire others by sharing your personal journey of faith and experience.</p>
                            <a href="share_testimony.php" class="btn btn-primary-custom btn-sm cta-button-test">Share Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">What Our Users Say</h2>
            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="testimonial-card p-4 text-center">
                                    <img src="https://i.pravatar.cc/150?img=1" alt="User 1" class="testimonial-img rounded-circle mb-3">
                                    <p class="lead">"This platform has been a blessing for our community. It's so easy to use and has helped us stay connected and grow in faith."</p>
                                    <h5 class="mt-3">- Sarah W.</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="testimonial-card p-4 text-center">
                                    <img src="https://i.pravatar.cc/150?img=2" alt="User 2" class="testimonial-img rounded-circle mb-3">
                                    <p class="lead">"I love the focus on praise and worship. It's a great way to connect with God and with others in a truly inspiring virtual space."</p>
                                    <h5 class="mt-3">- John D.</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="testimonial-card p-4 text-center">
                                    <img src="https://i.pravatar.cc/150?img=3" alt="User 3" class="testimonial-img rounded-circle mb-3">
                                    <p class="lead">"The best virtual meeting platform I've ever used. It's so simple, intuitive, and creates a powerful atmosphere for spiritual connection."</p>
                                    <h5 class="mt-3">- Emily R.</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0">&copy; 2026 Virtual Praise Room. All Rights Reserved.</p>
                    <p class="small text-muted mb-0">Loveworld City, Asese, Ogun State, Nigeria</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline footer-links">
                        <li class="list-inline-item"><a href="#">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="#">Terms of Service</a></li>
                        <li class="list-inline-item"><a href="about.php">About Us</a></li>
                        <li class="list-inline-item"><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            
            function handleScroll() {
                if (window.scrollY > 50) {
                    navbar.classList.add('navbar-scrolled');
                } else {
                    navbar.classList.remove('navbar-scrolled');
                }
            }

            window.addEventListener('scroll', handleScroll);
            handleScroll(); // Initial check
        });
    </script>
