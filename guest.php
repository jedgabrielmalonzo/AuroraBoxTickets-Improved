<?php
require_once __DIR__ . '/config.php';
// guest.php - View-only version of your site
$isGuest = true; // Flag to identify guest users

// Create connection
$conn = require __DIR__ . '/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current year for queries
$currentYear = date("Y");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuroraBox</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/landingpage.css" />
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/movies.css">
</head>

<style>
.showing-text {
    text-align: center;
    margin: 100px 0 30px;
    font-size: 2.4rem;
    font-weight: 800;
    color: #black;
    text-transform: uppercase;
    letter-spacing: 2px;
    position: relative;
    display: inline-block;
    width: 100%;
    font-family: 'Arial Black', sans-serif;
}

/* ===== Carousel Container ===== */
.movie-carousel-container {
    width: 100%;
    overflow: hidden;
    position: relative;
    padding: 20px 0 40px;
}

/* ===== Carousel Track ===== */
.movie-carousel-track {
    display: flex;
    gap: 20px;
    animation: scroll 30s linear infinite;
    width: calc(250px * 24);
    padding: 10px 0;
}

/* ===== Card Styles ===== */
.movie-carousel-card {
    width: 230px;            
    height: 400px;           
    background: #2d2d2d;     
    color: white;            
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.movie-carousel-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
}

/* ===== Card Image Container ===== */
.card-image-container {
    height: 250px;         
    overflow: hidden;
}

.carousel-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.movie-carousel-card:hover .carousel-card-img {
    transform: scale(1.05);
}

/* ===== Event Type Badge ===== */
.event-type-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    z-index: 2;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
}

.event-type-badge[data-type="park"] {
    background: #ff9e00;
}

.movie-carousel-card:hover .event-type-badge {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

/* ===== Card Body ===== */
.carousel-card-body {
    padding: 16px;
    background: #1f1f1f;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.carousel-card-title {
    font-size: 1.1rem;
    margin: 0;
    color: white;            
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ===== Animation ===== */
@keyframes scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(calc(-50% - 10px)); }
}

/* ===== Cinema Card Styles ===== */
.cinema-card-container {
    background: #1a1a1a;
    padding: 50px 0;
}

.cinema-text {
    color: white;
    font-size: 2.4rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 50px;
}

.cinema-card {
    background: #2d2d2d;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.cinema-card .header {
    background: #e50914;
    color: white;
    padding: 15px;
    font-weight: bold;
    font-size: 1.2rem;
}

.cinema-card .content {
    color: white;
}

.movie-poster2 {
    width: 100px;
    height: 140px;
    object-fit: cover;
    border-radius: 5px;
}

.movie-info h2 {
    color: white;
    font-size: 1.4rem;
    margin-bottom: 10px;
}

.movie-info p {
    color: #ccc;
    margin: 5px 0;
}

/* ===== Movie Card Styles ===== */
.movie-card-container {
    background: #f8f9fa;
    padding: 50px 0;
}

.movie-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.movie-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.movie-card .card-img-top {
    height: 300px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.movie-card:hover .card-img-top {
    transform: scale(1.05);
}

/* ===== Modal Styles ===== */
#signupPromptModal .modal-content {
    background-color: #2d2d2d;
    color: #fff;
    border-radius: 16px;
    padding: 32px 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
}

#signupPromptModal .modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
}

#signupPromptModal .modal-body p {
    font-size: 1rem;
    margin-bottom: 24px;
}

#signupPromptModal .btn-primary {
    background-color: #e50914;
    border: none;
    font-weight: 600;
    font-size: 1rem;
    padding: 10px 20px;
    border-radius: 10px;
    transition: background-color 0.2s ease-in-out;
}

#signupPromptModal .btn-primary:hover {
    background-color: #b20710;
}

.event-background {
    background: #49326B;
    background: radial-gradient(circle,rgba(73, 50, 107, 1) 5%, rgba(28, 28, 84, 1) 70%, rgba(13, 23, 48, 1) 99%);
}

.btn-custom {
    background-color: #6C63FF; 
    border-color: #6C63FF;
    color: white;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-custom:hover {
    background-color: #564FD8; 
    border-color: #564FD8;
    transform: translateY(-2px);  
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .movie-carousel-card {
        min-width: 200px;
    }
    
    .showing-text {
        font-size: 1.8rem;
    }
    
    .cinema-text {
        font-size: 1.8rem;
    }
}
</style>
    
<body>
    <!--===== HEADER =====-->
    <header class="l-header">
        <nav class="nav bd-grid">
            <div>
                <a href="#" class="nav__logo">
                    <img src="/images/logo.png" alt="Logo" style="height: 40px;">
                </a>
            </div>
            
            <div class="nav__menu" id="nav-menu">
                <ul class="nav__list">
                    <li class="nav__item">
                        <a href="index.php" class="nav__link ">Home</a>
                    </li>
                    <li class="nav__item">
                        <a href="guest.php" class="nav__link active-link">Tickets</a>
                    </li>
                    <li class="nav__item">
                        <a href="login.php" class="nav__link">Log In</a>
                    </li>
                    <li class="nav__item">
                        <a href="signup.php" class="nav__link">Sign Up</a>
                    </li>
                </ul>
            </div>

            <div class="nav__toggle" id="nav-toggle">
                <i class="bx bx-menu"></i>
            </div>
        </nav>
        <!-- Disclaimer Bar -->
        <div class="disclaimer">
            ⚠️ This website is a student project. All content is for educational purposes only.
        </div>
    </header>  

    <!----GET YOUR TICKETS---->
    <section class="event-background">
        <h1 class="showing-text">BROWSE PARKS</h1>

        <div class="movie-carousel-container">
            <div class="movie-carousel-track">
                <?php
                // Query only parks from your database
                $sql = "SELECT p.id, p.name, p.description, p.pictures, p.category, p.subcategory, p.city, p.country 
                        FROM parks p 
                        ORDER BY RAND() 
                        LIMIT 12";

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Get the first image from the pictures field
                        $images = explode(',', $row['pictures']);
                        $firstImage = trim($images[0]);
                        
                        echo '<div class="movie-carousel-card" onclick="showGuestModal(\'' . 
                             htmlspecialchars($row['name']) . '\', \'' . 
                             htmlspecialchars($row['description']) . '\', \'' . 
                             htmlspecialchars($firstImage) . '\', \'park\')">';
                        echo '<div class="card-image-container">';
                        echo '<img src="' . htmlspecialchars($firstImage) . '" class="carousel-card-img" alt="' . htmlspecialchars($row['name']) . '" loading="lazy">';
                        echo '</div>';
                        echo '<div class="carousel-card-body">';
                        echo '<h5 class="carousel-card-title">' . htmlspecialchars($row['name']) . '</h5>';
                        echo '<div class="event-type-badge" data-type="park">PARK</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    // Duplicate the cards for seamless loop
                    $conn->data_seek(0); // Reset result pointer
                    while ($row = $result->fetch_assoc()) {
                        $images = explode(',', $row['pictures']);
                        $firstImage = trim($images[0]);
                        
                        echo '<div class="movie-carousel-card" onclick="showGuestModal(\'' . 
                             htmlspecialchars($row['name']) . '\', \'' . 
                             htmlspecialchars($row['description']) . '\', \'' . 
                             htmlspecialchars($firstImage) . '\', \'park\')">';
                        echo '<div class="card-image-container">';
                        echo '<img src="' . htmlspecialchars($firstImage) . '" class="carousel-card-img" alt="' . htmlspecialchars($row['name']) . '" loading="lazy">';
                        echo '</div>';
                        echo '<div class="carousel-card-body">';
                        echo '<h5 class="carousel-card-title">' . htmlspecialchars($row['name']) . '</h5>';
                        echo '<div class="event-type-badge" data-type="park">PARK</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo "<p class='text-center text-white'>No parks available right now.</p>";
                }
                ?>
            </div>
        </div>

        <!----FEATURED PARKS---->
        <div class="cinema-card-container">
            <div class="container my-5">
                <h1 class="cinema-text text-center">FEATURED PARKS</h1>
                <div class="row g-1">
                    <?php
                    $sql = "SELECT p.*, c.category_name, s.subcategory_name 
                            FROM parks p 
                            LEFT JOIN category c ON p.category = c.category_id 
                            LEFT JOIN subcategory s ON p.subcategory = s.subcategory_id 
                            ORDER BY p.created_at DESC 
                            LIMIT 4";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        $parkIndex = 1;
                        while ($row = $result->fetch_assoc()) {
                            $images = explode(',', $row['pictures']);
                            $firstImage = trim($images[0]);
                            
                            echo '<div class="col-12 col-md-6">';
                            echo '<div class="cinema-card">';
                            echo '<div class="header text-center">FEATURED PARK ' . $parkIndex . '</div>';
                            echo '<div class="content d-flex align-items-start p-3">';
                            echo '<img src="' . htmlspecialchars($firstImage) . '" alt="' . htmlspecialchars($row['name']) . '" class="movie-poster2 me-3">';
                            echo '<div class="movie-info">';
                            echo '<h2>' . htmlspecialchars($row['name']) . '</h2>';
                            echo '<p><strong>Category:</strong> ' . htmlspecialchars($row['category_name'] ?? 'N/A') . '</p>';
                            echo '<p><strong>Type:</strong> ' . htmlspecialchars($row['subcategory_name'] ?? 'N/A') . '</p>';
                            echo '<p><strong>Location:</strong> ' . htmlspecialchars($row['city']) . ', ' . htmlspecialchars($row['country']) . '</p>';
                            echo '</div></div></div></div>';
                            $parkIndex++;
                        }
                    } else {
                        echo '<p class="text-center text-white">No featured parks found.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!---BROWSE BY CATEGORIES SECTION--->
        <h1 class="showing-text">BROWSE BY CATEGORIES</h1>
        <div class="movie-card-container">
            <div class="container my-5">
                <div class="row">
                    <?php
                    // Get all categories with park count
                    $sql = "SELECT c.*, COUNT(p.id) as park_count 
                            FROM category c 
                            LEFT JOIN parks p ON c.category_id = p.category 
                            GROUP BY c.category_id 
                            ORDER BY c.category_name";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        $categoryImages = [
                            'Theme Parks' => 'https://images.unsplash.com/photo-1544717297-fa95b6ee9643?w=500&h=300&fit=crop',
                            'Aqua Parks' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=500&h=300&fit=crop',
                            'Nature Parks' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=500&h=300&fit=crop',
                            'Museums' => 'https://images.unsplash.com/photo-1565301660306-29e08751cc53?w=500&h=300&fit=crop'
                        ];

                        while($row = $result->fetch_assoc()) {
                            $categoryImage = $categoryImages[$row['category_name']] ?? 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=500&h=300&fit=crop';
                            
                            echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4 d-flex justify-content-center">';
                            echo '<div class="card movie-card" onclick="showCategoryModal(\'' . 
                                 htmlspecialchars($row['category_name']) . '\', \'' . 
                                 htmlspecialchars($row['category_id']) . '\', \'' . 
                                 $row['park_count'] . '\')">';
                            echo '<img src="' . $categoryImage . '" class="card-img-top" alt="' . htmlspecialchars($row['category_name']) . '">';
                            echo '<div class="card-body text-center">';
                            echo '<h5 class="card-title">' . htmlspecialchars($row['category_name']) . '</h5>';
                            echo '<p class="card-text"><span class="badge bg-primary">' . $row['park_count'] . ' Parks</span></p>';
                            echo '</div></div></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!---BROWSE BY SUBCATEGORIES SECTION--->
        <h1 class="showing-text">EXPLORE PARK TYPES</h1>
        <div class="movie-card-container" style="background: #e9ecef;">
            <div class="container my-5">
                <?php
                // Get categories with their subcategories
                $sql = "SELECT c.category_name, c.category_id, s.subcategory_name, s.subcategory_id, COUNT(p.id) as park_count
                        FROM category c 
                        LEFT JOIN subcategory s ON c.category_id = s.category_id 
                        LEFT JOIN parks p ON s.subcategory_id = p.subcategory 
                        GROUP BY c.category_id, s.subcategory_id 
                        ORDER BY c.category_name, s.subcategory_name";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    $currentCategory = '';
                    $subcategoryImages = [
                        'Fantasy' => 'https://images.unsplash.com/photo-1596474396034-d0708b2d860b?w=300&h=200&fit=crop',
                        'Carnival' => 'https://images.unsplash.com/photo-1520637836862-4d197d17c883?w=300&h=200&fit=crop',
                        'Animal' => 'https://images.unsplash.com/photo-1549366021-9f761d040be1?w=300&h=200&fit=crop',
                        'Water Spa' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=300&h=200&fit=crop',
                        'Hot Spring' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=200&fit=crop',
                        'Forest Park' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=300&h=200&fit=crop',
                        'Geological' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=300&h=200&fit=crop',
                        'Art' => 'https://images.unsplash.com/photo-1565301660306-29e08751cc53?w=300&h=200&fit=crop'
                    ];

                    while($row = $result->fetch_assoc()) {
                        if ($row['subcategory_name'] == null) continue;
                        
                        if ($currentCategory != $row['category_name']) {
                            if ($currentCategory != '') {
                                echo '</div></div>'; // Close previous category
                            }
                            $currentCategory = $row['category_name'];
                            echo '<div class="mb-5">';
                            echo '<h2 class="text-center mb-4" style="color: #495057; font-weight: 700;">' . htmlspecialchars($currentCategory) . '</h2>';
                            echo '<div class="row">';
                        }
                        
                        $subcategoryImage = $subcategoryImages[$row['subcategory_name']] ?? 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=300&h=200&fit=crop';
                        
                        echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">';
                        echo '<div class="card h-100" style="border-radius: 15px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" onclick="showSubcategoryModal(\'' . 
                             htmlspecialchars($row['subcategory_name']) . '\', \'' . 
                             htmlspecialchars($row['category_name']) . '\', \'' . 
                             htmlspecialchars($row['subcategory_id']) . '\', \'' . 
                             $row['park_count'] . '\')">';
                        echo '<img src="' . $subcategoryImage . '" class="card-img-top" style="height: 150px; object-fit: cover;" alt="' . htmlspecialchars($row['subcategory_name']) . '">';
                        echo '<div class="card-body text-center p-3">';
                        echo '<h6 class="card-title mb-2">' . htmlspecialchars($row['subcategory_name']) . '</h6>';
                        echo '<small class="text-muted">' . $row['park_count'] . ' ' . ($row['park_count'] == 1 ? 'Park' : 'Parks') . '</small>';
                        echo '</div></div></div>';
                    }
                    
                    if ($currentCategory != '') {
                        echo '</div></div>'; // Close last category
                    }
                }
                ?>
            </div>
        </div>

        <!---ALL PARKS SECTION--->
        <h1 class="showing-text">ALL PARKS</h1>
        <div class="movie-card-container">
            <div class="container my-5">
                <div class="row">
                    <?php
                    $sql = "SELECT p.*, c.category_name, s.subcategory_name 
                            FROM parks p 
                            LEFT JOIN category c ON p.category = c.category_id 
                            LEFT JOIN subcategory s ON p.subcategory = s.subcategory_id 
                            ORDER BY p.name ASC";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $images = explode(',', $row['pictures']);
                            $firstImage = trim($images[0]);
                            
                            echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4 d-flex justify-content-center">';
                            echo '<div class="card movie-card" onclick="showGuestModal(\'' . 
                                 htmlspecialchars($row['name']) . '\', \'' . 
                                 htmlspecialchars($row['description']) . '\', \'' . 
                                 htmlspecialchars($firstImage) . '\', \'park\')">';
                            echo '<img src="' . htmlspecialchars($firstImage) . '" class="card-img-top" alt="' . htmlspecialchars($row['name']) . '">';
                            echo '<div class="card-body">';
                            echo '<h5 class="card-title">' . htmlspecialchars($row['name']) . '</h5>';
                            echo '<p class="card-text"><small class="text-muted">' . htmlspecialchars($row['category_name'] ?? 'Park') . '</small></p>';
                            echo '</div></div></div>';
                        }
                    } else {
                        echo "<p class='text-center'>No parks found.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Guest Modal -->
    <div class="modal fade" id="guestModal" tabindex="-1" aria-labelledby="guestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="guestModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <img id="guestModalImage" src="" alt="" class="img-fluid rounded">
                        </div>
                        <div class="col-md-8">
                            <p id="guestModalDescription"></p>
                            <div class="alert alert-info">
                                <strong>Want to visit this park?</strong> 
                                Please <a href="login.php" class="alert-link">login</a> or 
                                <a href="signup.php" class="alert-link">register</a> to book tickets!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="categoryModalContent"></div>
                    <div class="alert alert-info mt-3">
                        <strong>Explore this category!</strong> 
                        Please <a href="login.php" class="alert-link">login</a> or 
                        <a href="signup.php" class="alert-link">register</a> to book tickets for any of these parks!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subcategory Modal -->
    <div class="modal fade" id="subcategoryModal" tabindex="-1" aria-labelledby="subcategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subcategoryModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="subcategoryModalContent"></div>
                    <div class="alert alert-info mt-3">
                        <strong>Interested in this type of park?</strong> 
                        Please <a href="login.php" class="alert-link">login</a> or 
                        <a href="signup.php" class="alert-link">register</a> to explore and book tickets!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sign Up Prompt Modal -->
    <div class="modal fade" id="signupPromptModal" tabindex="-1" aria-labelledby="signupPromptLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header border-0">
                    <h5 class="modal-title w-100" id="signupPromptLabel">Want To Book Tickets?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4">Sign up now to book your park tickets and enjoy amazing experiences!</p>
                    <a href="signup.php" class="btn btn-custom d-inline-flex align-items-center gap-2">
                        <img src="/images/checkbox.png" alt="Sign Up Icon" style="width: 24px; height: 24px;">
                        Sign Up
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Show guest modal with park details
    function showGuestModal(name, description, imageUrl, type) {
        document.getElementById('guestModalTitle').textContent = name;
        document.getElementById('guestModalDescription').textContent = description;
        document.getElementById('guestModalImage').src = imageUrl;
        document.getElementById('guestModalImage').alt = name;
        
        const modal = new bootstrap.Modal(document.getElementById('guestModal'));
        modal.show();
    }

    // Show category modal with category details
    function showCategoryModal(categoryName, categoryId, parkCount) {
        document.getElementById('categoryModalTitle').textContent = categoryName;
        
        const content = `
            <div class="text-center">
                <h4>Category: ${categoryName}</h4>
                <p class="lead">${parkCount} amazing parks await you in this category!</p>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h5 class="card-title">Parks Available</h5>
                                <h2 class="text-primary">${parkCount}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h5 class="card-title">Category Type</h5>
                                <h6 class="text-success">${categoryName}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('categoryModalContent').innerHTML = content;
        
        const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
        modal.show();
    }

    // Show subcategory modal with subcategory details
    function showSubcategoryModal(subcategoryName, categoryName, subcategoryId, parkCount) {
        document.getElementById('subcategoryModalTitle').textContent = subcategoryName;
        
        const descriptions = {
            'Fantasy': 'Step into magical worlds filled with wonder and imagination.',
            'Carnival': 'Experience the joy and excitement of traditional carnival attractions.',
            'Animal': 'Get up close with amazing wildlife and learn about nature.',
            'Haunted': 'Dare to enter spine-chilling experiences for thrill seekers.',
            'Children': 'Perfect family-friendly fun designed especially for kids.',
            'Water Spa': 'Relax and rejuvenate in therapeutic water experiences.',
            'Hot Spring': 'Enjoy natural thermal waters with healing properties.',
            'Inflatables': 'Bounce and play on exciting inflatable attractions.',
            'Resorts': 'Complete vacation destinations with multiple activities.',
            'River Adventure': 'Explore scenic waterways and aquatic adventures.',
            'Forest Park': 'Immerse yourself in natural forest environments.',
            'Geological': 'Discover amazing rock formations and natural wonders.',
            'Wildlife': 'Observe animals in their natural habitats.',
            'Subterranean': 'Explore fascinating underground cave systems.',
            'Aquatic': 'Dive into underwater worlds and marine life.',
            'Art': 'Appreciate creative works and cultural exhibitions.',
            'Heritage Homes': 'Step back in time and explore historical residences.',
            'Military': 'Learn about historical military heritage and artifacts.',
            'Sci-Tech': 'Discover the wonders of science and technology.',
            'Nature': 'Connect with the natural world and its beauty.'
        };
        
        const description = descriptions[subcategoryName] || 'Discover unique experiences in this special type of attraction.';
        
        const content = `
            <div class="text-center">
                <h4>${subcategoryName}</h4>
                <p class="text-muted mb-3">Category: ${categoryName}</p>
                <p class="lead">${description}</p>
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h5 class="card-title">Parks Available</h5>
                                <h2 class="text-primary">${parkCount}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body">
                                <h5 class="card-title">Category</h5>
                                <h6 class="text-success">${categoryName}</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-body">
                                <h5 class="card-title">Type</h5>
                                <h6 class="text-info">${subcategoryName}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('subcategoryModalContent').innerHTML = content;
        
        const modal = new bootstrap.Modal(document.getElementById('subcategoryModal'));
        modal.show();
    }

    // Show signup prompt after delay
    document.addEventListener("DOMContentLoaded", function () {
        const modal = new bootstrap.Modal(document.getElementById("signupPromptModal"));
        
        // Show modal after 3 seconds
        setTimeout(() => {
            modal.show();
        }, 3000);
    });

    // Auto-scrolling carousel
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.querySelector('.movie-carousel-track');
        if (!track) return;
        
        let scrollPos = 0;
        const speed = 1;
        
        function animate() {
            scrollPos -= speed;
            
            if (Math.abs(scrollPos) >= track.scrollWidth / 2) {
                scrollPos = 0;
            }
            
            track.style.transform = `translateX(${scrollPos}px)`;
            requestAnimationFrame(animate);
        }
        
        animate();
    });
    </script>

    <!--===== FOOTER =====-->
    <footer class="footer">
        <div class="footer__logo">
            <img src="/images/landingpage/logo.png" alt="Logo" style="height: 40px;">
        </div>
        <p class="footer__copy">&#169; Aurorabox. All rights reserved</p>
    </footer>

    <!--===== SCROLL REVEAL =====-->
    <script src="https://unpkg.com/scrollreveal"></script>

    <!--===== MAIN JS =====-->
    <script src="scripts/main.js"></script>

    <!---BOOTSTRAP-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <?php $conn->close(); ?>
</body>
</html>