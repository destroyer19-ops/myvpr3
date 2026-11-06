<?php
ini_set("display_errors", 1);

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? $_SESSION['username'] : '';
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="hero-section">
        <div class="container">
            <h1><span id="typed"></span></h1>
            <p>Connect with your community in a virtual space designed for praise, worship, and connection.</p>
            <?php if ($is_logged_in): ?>
                <a href="meeting-room.php" class="btn btn-primary-custom btn-custom">Start a Meeting</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary-custom btn-custom">Get Started</a>
                <a href="login.php" class="btn btn-secondary-custom btn-custom">Sign In</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Why Choose Virtual Praise Room?</h2>

            <div class="row align-items-center mb-5">
                <div class="col-md-6" data-aos="fade-right">
                    <img src="https://images.unsplash.com/photo-1517486804500-be25f1b82c73?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="img-fluid rounded shadow-lg" alt="Community Focused">
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <h3 class="mb-3">Community Focused</h3>
                    <p>Virtual Praise Room is meticulously designed to foster a profound sense of community and connection among users. We believe in the power of collective worship and provide tools that bring people closer, no matter the distance.</p>
                </div>
            </div>

            <div class="row align-items-center flex-row-reverse mb-5">
                <div class="col-md-6" data-aos="fade-left">
                    <img src="https://images.unsplash.com/photo-1517486804500-be25f1b82c73?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="img-fluid rounded shadow-lg" alt="Praise & Worship">
                </div>
                <div class="col-md-6" data-aos="fade-right">
                    <h3 class="mb-3">Dedicated Praise & Worship Space</h3>
                    <p>Experience an unparalleled environment crafted specifically for praise and worship. Our platform offers unique features to enhance your spiritual journey, allowing you to connect with the divine and fellow worshippers in a truly immersive way.</p>
                </div>
            </div>

            <div class="row align-items-center mb-5">
                <div class="col-md-6" data-aos="fade-right">
                    <img src="https://images.unsplash.com/photo-1517486804500-be25f1b82c73?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="img-fluid rounded shadow-lg" alt="Secure & Private">
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <h3 class="mb-3">Secure & Private Interactions</h3>
                    <p>Your spiritual gatherings are kept private and secure with state-of-the-art encryption. We prioritize your peace of mind, ensuring that your moments of worship and fellowship remain intimate and protected from external intrusions.</p>
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
                        <a href="crusade.php" class="btn btn-primary-custom btn-custom">Start a Crusade</a>
                    </div>
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <div class="cta-block p-5 text-center rounded shadow-lg h-100">
                        <h3 class="mb-3">Watch Live TV</h3>
                        <p class="mb-4">Join us for our live broadcasts and experience the power of praise and worship.</p>
                        <a href="live_tv.php" class="btn btn-primary-custom btn-custom">Watch Now</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">What Our Users Say</h2>
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

    <!-- Call to Action Section -->
    <section class="cta-section" data-aos="zoom-in">
        <div class="container">
            <h2>Ready to get started?</h2>
            <p>Create an account today and start connecting with your community.</p>
            <a href="register.php" class="btn btn-primary-custom btn-custom">Sign Up Now</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
            <p class="mt-3">&copy; 2025 Virtual Praise Room. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <script>
        var typed = new Typed('#typed', {
            strings: ['Experience the Joy of Fellowship', 'Connect with Your Community', 'Praise, Worship, and Connection'],
            typeSpeed: 50,
            backSpeed: 25,
            loop: true
        });
    </script>
<?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
