<?php
/**
 * FarmSaathi - Landing Page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmSaathi - Smart Farm Management System</title>
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <img src="assets/images/logo.jpg" alt="FarmSaathi Logo" onerror="this.style.display='none'">
                <span>FarmSaathi</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#home">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="auth/login.php" class="btn-login">Login</a></li>
                <li><a href="auth/signup.php" class="btn-signup">Get Started</a></li>
            </ul>
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Manage Your Farm <span class="highlight">Smarter</span></h1>
                <p class="hero-subtitle">Join 50+ farms across Nepal managing 1,250+ livestock and 150+ hectares with our complete farm management solution</p>
                <div class="hero-buttons">
                    <a href="auth/signup.php" class="btn btn-primary">Start Free Trial</a>
                    <a href="#services" class="btn btn-secondary">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/images/farmerhappy.jpg" alt="Happy Farmer" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="fas fa-user-tie" style="display: none;"></i>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <p class="section-subtitle">Everything you need to run a successful farm</p>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-cow"></i></div>
                    <h3>Livestock Management</h3>
                    <p>Track animals, health records, breeding, production, and sales with ease</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-seedling"></i></div>
                    <h3>Crop Management</h3>
                    <p>Monitor crop cycles, planting schedules, harvest data, and yields</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-boxes"></i></div>
                    <h3>Inventory Control</h3>
                    <p>Manage feed, supplies, equipment, and get low-stock alerts</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Financial Tracking</h3>
                    <p>Track income, expenses, and generate profit/loss reports</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-users"></i></div>
                    <h3>Employee Management</h3>
                    <p>Manage staff, track attendance, and handle payroll efficiently</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-file-pdf"></i></div>
                    <h3>Reports & Analytics</h3>
                    <p>Generate detailed PDF reports and visualize farm performance</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2 class="section-title">Why Choose FarmSaathi?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Mobile Friendly</h3>
                    <p>Access from any device, anywhere</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-lock"></i>
                    <h3>Secure & Private</h3>
                    <p>Your data is protected with encryption</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-clock"></i>
                    <h3>Real-time Updates</h3>
                    <p>Get instant notifications and alerts</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-cloud"></i>
                    <h3>Cloud Backup</h3>
                    <p>Never lose your important farm data</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Smart Analytics</h3>
                    <p>Make data-driven decisions</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-headset"></i>
                    <h3>24/7 Support</h3>
                    <p>We're here to help you succeed</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3 class="counter" data-target="50">50</h3>
                    <p>Active Farms</p>
                </div>
                <div class="stat-item">
                    <h3 class="counter" data-target="1250">1,250</h3>
                    <p>Livestock Tracked</p>
                </div>
                <div class="stat-item">
                    <h3><span class="counter" data-target="95">95</span>%</h3>
                    <p>Satisfaction Rate</p>
                </div>
                <div class="stat-item">
                    <h3 class="counter" data-target="150">150</h3>
                    <p>Hectares Managed</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About FarmSaathi</h2>
                    <p>FarmSaathi is your trusted companion in modern farm management. We understand the challenges farmers face daily, and we've built a comprehensive solution to simplify farm operations.</p>
                    <p>Our platform combines traditional farming wisdom with cutting-edge technology to help you increase productivity, reduce costs, and make informed decisions.</p>
                    <ul class="about-list">
                        <li><i class="fas fa-check"></i> Easy to use interface</li>
                        <li><i class="fas fa-check"></i> Comprehensive farm tracking</li>
                        <li><i class="fas fa-check"></i> Affordable pricing</li>
                        <li><i class="fas fa-check"></i> Regular updates & improvements</li>
                    </ul>
                </div>
                <div class="about-image">
                    <i class="fas fa-leaf"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Farmers Say</h2>
            <p class="section-subtitle">Real stories from real farmers using FarmSaathi</p>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <p class="testimonial-text">"FarmSaathi has transformed how I manage my dairy farm. Tracking 50+ cattle, milk production and health records is now effortless. My productivity increased by 30%!"</p>
                    <h4 class="testimonial-name">Ram Bahadur Thapa</h4>
                    <p class="testimonial-location">Dairy Farmer, Chitwan (120 cattle)</p>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <p class="testimonial-text">"Managing 5,000 chickens was overwhelming until I found FarmSaathi. The inventory alerts and feed tracking save me hours every week and thousands in costs."</p>
                    <h4 class="testimonial-name">Sita Devi Sharma</h4>
                    <p class="testimonial-location">Poultry Farmer, Jhapa (5,000 birds)</p>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <p class="testimonial-text">"With 25 hectares of crops and 80 goats, keeping track was impossible. FarmSaathi's financial reports showed me I was losing money on certain crops. Game changer!"</p>
                    <h4 class="testimonial-name">Krishna Prasad Poudel</h4>
                    <p class="testimonial-location">Mixed Farmer, Kaski (25 hectares)</p>
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Have questions? We'd love to hear from you</p>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h3>Address</h3>
                            <p>Rampur, Palpa<br>Nepal</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h3>Phone</h3>
                            <p>+977 9847403600<br>Mon-Fri 9am-6pm</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h3>Email</h3>
                            <p>farmsathi@gmail.com<br>Contact us anytime</p>
                        </div>
                    </div>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                <form class="contact-form" id="contactForm">
                    <div class="form-group">
                        <input type="text" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>FarmSaathi</h3>
                    <p>Your trusted partner in smart farm management</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Newsletter</h4>
                    <p>Subscribe for updates and tips</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Your email">
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 FarmSaathi. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/landing.js"></script>
</body>
</html>
