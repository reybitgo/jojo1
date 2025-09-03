<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOJO Token - Next Generation Digital Mining Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --dark-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --accent-color: #6c5ce7;
            --text-dark: #2d3436;
            --text-light: #636e72;
            --bg-light: #f8f9fa;
            --border-light: rgba(0,0,0,0.1);
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.7;
            color: var(--text-dark);
            background: var(--bg-light);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-light);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(108, 92, 231, 0.3));
            transition: all 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.1);
            filter: drop-shadow(0 4px 12px rgba(108, 92, 231, 0.5));
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(108, 92, 231, 0.1);
            color: var(--accent-color) !important;
        }

        .btn-login {
            background: var(--primary-gradient);
            border: none;
            color: white !important;
            font-weight: 600;
            padding: 0.75rem 1.5rem !important;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* Hero Section */
        .hero {
            background: var(--primary-gradient);
            color: white;
            padding: 120px 0;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,20 50,0 100,20 150,0 200,20 250,0 300,20 350,0 400,20 450,0 500,20 550,0 600,20 650,0 700,20 750,0 800,20 850,0 900,20 950,0 1000,20 1000,100 0,100"/></svg>') repeat-x;
            animation: wave 20s linear infinite;
        }

        @keyframes wave {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100px); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero .lead {
            font-size: 1.4rem;
            font-weight: 400;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }

        .btn-hero {
            background: white;
            color: var(--accent-color);
            font-weight: 600;
            font-size: 1.1rem;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-soft);
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            color: var(--accent-color);
        }

        /* Stats Section */
        .stats {
            background: white;
            padding: 80px 0;
            margin-top: -60px;
            position: relative;
            z-index: 3;
        }

        .stats-card {
            text-align: center;
            padding: 2rem;
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-light);
            font-weight: 500;
        }

        /* Features Section */
        .features {
            padding: 100px 0;
            background: var(--bg-light);
        }

        .section-title {
            font-size: 2.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--text-dark);
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 4rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            height: 100%;
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .feature-text {
            color: var(--text-light);
            line-height: 1.6;
        }

        /* How It Works */
        .how-it-works {
            padding: 100px 0;
            background: white;
        }

        .step {
            text-align: center;
            padding: 2rem;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .step-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .step-text {
            color: var(--text-light);
        }

        /* Testimonials */
        .testimonials {
            padding: 100px 0;
            background: var(--bg-light);
        }

        .testimonial {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            height: 100%;
        }

        .testimonial:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .testimonial-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            object-fit: cover;
            border: 4px solid var(--accent-color);
        }

        .testimonial-text {
            font-style: italic;
            margin-bottom: 1.5rem;
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .testimonial-author {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* CTA Section */
        .cta {
            background: var(--dark-gradient);
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 30px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-section a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--accent-color);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 2rem;
            text-align: center;
            color: #ccc;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero .lead {
                font-size: 1.2rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .navbar-brand {
                font-size: 1.5rem;
            }

            .navbar-brand img {
                width: 32px;
                height: 32px;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }

        /* Return to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .back-to-top:active {
            transform: translateY(-2px);
        }

        /* Progress ring animation */
        .back-to-top::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 3px solid transparent;
            border-top-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            transform: rotate(-90deg);
            transition: all 0.3s ease;
        }

        .back-to-top:hover::before {
            border-top-color: white;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(-90deg); }
            100% { transform: rotate(270deg); }
        }

        /* Ripple effect for back to top button */
        .back-to-top .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="assets/images/logo3.png" alt="JOJO Token Logo">
            <span>JOJO Token</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how-it-works">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#testimonials">Testimonials</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>
                <li class="nav-item ms-2">
                    <a class="nav-link btn-register" href="register.php">
                        <i class="fas fa-user-plus me-1"></i>Register
                    </a>
                </li>
                <li class="nav-item ms-2">
                    <a class="nav-link btn-login" href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content fade-in">
            <h1>Next Generation<br>Digital Mining Platform</h1>
            <p class="lead">Join JOJO Token and experience the future of secure, efficient, and rewarding token mining with cutting-edge technology.</p>
            <a href="register.php" class="btn btn-hero">
                <i class="fas fa-rocket me-2"></i>Start Mining Now
            </a>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="stats-card fade-in animate-delay-1">
                    <div class="stats-number">50K+</div>
                    <div class="stats-label">Active Miners</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in animate-delay-2">
                    <div class="stats-number">$2.5M</div>
                    <div class="stats-label">Tokens Mined</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in animate-delay-3">
                    <div class="stats-number">99.9%</div>
                    <div class="stats-label">Uptime</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in animate-delay-3">
                    <div class="stats-number">24/7</div>
                    <div class="stats-label">Support</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features" id="features">
    <div class="container">
        <h2 class="section-title">Why Choose JOJO Token?</h2>
        <p class="section-subtitle">Experience unparalleled security, performance, and profitability in digital mining</p>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5 class="feature-title">Bank-Grade Security</h5>
                    <p class="feature-text">Advanced encryption and multi-layer security protocols protect your investments and personal data with military-grade protection.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h5 class="feature-title">Lightning Fast Performance</h5>
                    <p class="feature-text">Our optimized mining algorithms and high-performance infrastructure ensure maximum efficiency and profitability.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5 class="feature-title">Real-Time Analytics</h5>
                    <p class="feature-text">Monitor your mining progress, earnings, and performance metrics with our comprehensive real-time dashboard.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h5 class="feature-title">Mobile Optimized</h5>
                    <p class="feature-text">Access your mining operations anywhere, anytime with our fully responsive mobile-optimized platform.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="feature-title">Referral Rewards</h5>
                    <p class="feature-text">Earn additional tokens by referring friends and building your mining network with our generous referral program.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h5 class="feature-title">24/7 Expert Support</h5>
                    <p class="feature-text">Our dedicated support team is available around the clock to assist you with any questions or technical issues.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <h2 class="section-title">How JOJO Token Works</h2>
        <p class="section-subtitle">Get started in three simple steps and begin earning tokens immediately</p>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step">
                    <div class="step-number">1</div>
                    <h5 class="step-title">Create Your Account</h5>
                    <p class="step-text">Sign up with your email and complete our quick verification process to secure your account.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step">
                    <div class="step-number">2</div>
                    <h5 class="step-title">Choose Mining Package</h5>
                    <p class="step-text">Select from our range of mining packages that best fit your investment goals and risk tolerance.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step">
                    <div class="step-number">3</div>
                    <h5 class="step-title">Start Earning</h5>
                    <p class="step-text">Begin mining immediately and watch your tokens grow with our automated mining system.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials" id="testimonials">
    <div class="container">
        <h2 class="section-title">What Our Miners Say</h2>
        <p class="section-subtitle">Join thousands of satisfied miners who trust JOJO Token</p>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="testimonial">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=face" class="testimonial-img" alt="John D.">
                    <p class="testimonial-text">"JOJO Token has revolutionized my investment strategy. The returns are consistent and the platform is incredibly user-friendly."</p>
                    <h6 class="testimonial-author">John D. - Professional Trader</h6>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=face"
     class="testimonial-img"
     alt="Sarah M.">
                    <p class="testimonial-text">"The security features give me complete peace of mind. I've been mining with JOJO Token for over a year with excellent results."</p>
                    <h6 class="testimonial-author">Sarah M. - Crypto Enthusiast</h6>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial">
                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face" class="testimonial-img" alt="Michael R.">
                    <p class="testimonial-text">"Outstanding platform with professional support. JOJO Token has exceeded all my expectations in terms of performance and reliability."</p>
                    <h6 class="testimonial-author">Michael R. - Investment Advisor</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta" id="contact">
    <div class="container">
        <h2>Ready to Start Your Mining Journey?</h2>
        <p>Join over 50,000 miners who are already earning with JOJO Token. Start your profitable mining operation today.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="register.php" class="btn btn-hero">
                <i class="fas fa-user-plus me-2"></i>Create Account
            </a>
            <a href="#features" class="btn btn-outline-light btn-lg">
                <i class="fas fa-info-circle me-2"></i>Learn More
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="fas fa-coins me-2"></i>JOJO Token</h5>
                <p>The next generation digital mining platform designed for maximum security, efficiency, and profitability.</p>
            </div>
            <div class="footer-section">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="register.php">Get Started</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h5>Support</h5>
                <ul class="list-unstyled">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Documentation</a></li>
                    <li><a href="#">API Reference</a></li>
                    <li><a href="#">Contact Support</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h5>Legal</h5>
                <ul class="list-unstyled">
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Risk Disclosure</a></li>
                    <li><a href="#">Compliance</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 JOJO Token. All rights reserved. | Secure • Reliable • Profitable</p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" title="Return to top">
    <i class="fas fa-chevron-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(255,255,255,0.98)';
        navbar.style.backdropFilter = 'blur(20px)';
    } else {
        navbar.style.background = 'rgba(255,255,255,0.95)';
    }
});

// Counter animation for stats
function animateCounters() {
    const counters = document.querySelectorAll('.stats-number');
    const speed = 200;

    counters.forEach(counter => {
        const target = counter.innerText;
        const value = target.replace(/[^0-9.]/g, '');
        const increment = parseFloat(value) / speed;

        let current = 0;
        const timer = setInterval(() => {
            current += increment;
            if (current >= parseFloat(value)) {
                counter.innerText = target;
                clearInterval(timer);
            } else {
                if (target.includes('K')) {
                    counter.innerText = Math.floor(current) + 'K+';
                } else if (target.includes('M')) {
                    counter.innerText = '$' + (current / 1000000).toFixed(1) + 'M';
                } else if (target.includes('%')) {
                    counter.innerText = current.toFixed(1) + '%';
                } else {
                    counter.innerText = target;
                }
            }
        }, 10);
    });
}

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
            if (entry.target.closest('.stats')) {
                animateCounters();
            }
        }
    });
}, observerOptions);

// Observe all fade-in elements
document.querySelectorAll('.fade-in').forEach(el => {
    observer.observe(el);
});

// Back to Top Button functionality
const backToTopButton = document.getElementById('backToTop');

// Show/hide button based on scroll position
window.addEventListener('scroll', function() {
    if (window.scrollY > 300) {
        backToTopButton.classList.add('show');
    } else {
        backToTopButton.classList.remove('show');
    }
});

// Smooth scroll to top when button is clicked
backToTopButton.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Add ripple effect on click
backToTopButton.addEventListener('click', function(e) {
    const ripple = document.createElement('span');
    const rect = this.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');
    
    this.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
});
</script>

</body>
</html>