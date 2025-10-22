<?php
$mysqli = require __DIR__ . "/../database.php";

// Handle form submission to update schedules
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log all POST data for debugging
    error_log("POST Data: " . print_r($_POST, true));

    $schedule_id = $_POST['schedule_id'];
    $cinema = $_POST['cinema']; // Holds the table name (e.g., cinema1_date1_time1)
    $movie_id = $_POST['movie_id'];
    $showtime = $_POST['showtime']; // New showtime field
    $ticket_type = strtolower($_POST['ticket_type']); // Convert ticket type to lowercase

    // Debugging output
    error_log("Updating schedule ID: $schedule_id, Movie ID: $movie_id, Showtime: $showtime, Ticket Type: $ticket_type");

    // Set seat price based on ticket type
    $seat_price = ($ticket_type === 'vip') ? 500.00 : 350.00;

    // Update the schedule in the schedules table
    $sql = "UPDATE schedules SET movie_id = ?, showtime = ?, ticket_type = ? WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement for updating schedules: " . htmlspecialchars($mysqli->error));
    }

    // Bind parameters for the schedule update
    $stmt->bind_param("sssi", $movie_id, $showtime, $ticket_type, $schedule_id);
    $stmt->execute();

    // Check for execution errors
    if ($stmt->error) {
        die("Execute error: " . htmlspecialchars($stmt->error));
    } else {
        error_log("Successfully updated schedule ID: $schedule_id");
    }

    // Dynamically reset all statuses to 'available' in the corresponding cinema table
    $sql_reset_status = "UPDATE `$cinema` SET status = 'available'";
    $stmt_reset_status = $mysqli->prepare($sql_reset_status);
    if ($stmt_reset_status === false) {
        die("Error preparing statement for resetting status: " . htmlspecialchars($mysqli->error));
    }

    // Execute the reset status statement
    $stmt_reset_status->execute();

    // Check for execution errors
    if ($stmt_reset_status->error) {
        die("Execute error: " . htmlspecialchars($stmt_reset_status->error));
    }

    // Now update the seat price in the corresponding cinema table for all rows
    $sql_seat_price_update = "UPDATE `$cinema` SET seat_price = ?";
    $stmt_seat_price = $mysqli->prepare($sql_seat_price_update);
    if ($stmt_seat_price === false) {
        die("Error preparing statement for updating seat price: " . htmlspecialchars($mysqli->error));
    }

    // Bind parameters for the seat price update
    $stmt_seat_price->bind_param("d", $seat_price);
    $stmt_seat_price->execute();

    // Check for execution errors
    if ($stmt_seat_price->error) {
        die("Execute error: " . htmlspecialchars($stmt_seat_price->error));
    }

    // Redirect to the same page to see updates
    header("Location: maintenance.php");
    exit();
}

// Fetch all schedules
$sql_schedules = "SELECT * FROM schedules";
$result_schedules = $mysqli->query($sql_schedules);

// Check for query errors
if ($result_schedules === false) {
    die("Query error: " . htmlspecialchars($mysqli->error));
}

// Fetch all movies for the dropdown
$sql_movies = "SELECT id, title FROM movies";
$result_movies = $mysqli->query($sql_movies);

// Check for query errors
if ($result_movies === false) {
    die("Query error: " . htmlspecialchars($mysqli->error));
}

$movies = [];
while ($row = $result_movies->fetch_assoc()) {
    $movies[$row['id']] = $row['title']; // Store movie titles
}

// Group schedules by base cinema name (e.g., cinema1)
$cinema_schedules = [];
while ($schedule = $result_schedules->fetch_assoc()) {
    $cinema_key = preg_replace('/_.*$/', '', $schedule['cinema']); // Get the base cinema name
    $cinema_schedules[$cinema_key][] = $schedule; // Group by cinema base name
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h2>Edit Cinema Schedules</h2>

        <?php foreach ($cinema_schedules as $cinema => $schedules): ?>
            <div class="mb-4 border rounded p-3">
                <h4><?php echo htmlspecialchars($cinema); ?></h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cinema</th>
                            <th>Available Seats</th>
                            <th>Movie</th>
                            <th>Showtime</th>
                            <th>Ticket Type</th>
                            <th>Seat Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <form action="maintenance.php" method="POST" onsubmit="return confirmUpdate();">
                                    <td>
                                        <span><?php echo htmlspecialchars($schedule['cinema']); ?></span> <!-- Full cinema name -->
                                        <input type="hidden" name="cinema" value="<?php echo htmlspecialchars($schedule['cinema']); ?>">
                                    </td>
                                    <td>
                                        <span><?php echo htmlspecialchars($schedule['available_seats']); ?></span>
                                    </td>
                                    <td>
                                        <select name="movie_id" required>
                                            <option value="<?php echo htmlspecialchars($schedule['movie_id']); ?>">
                                                <?php echo htmlspecialchars($movies[$schedule['movie_id']]); ?>
                                            </option>
                                            <?php foreach ($movies as $id => $title): ?>
                                                <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="datetime-local" name="showtime" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['showtime'])); ?>" 
                                               required>
                                    </td>
                                    <td>
                                        <select name="ticket_type" onchange="updateSeatPrice(this)" required>
                                            <option value="vip" <?php echo strtolower($schedule['ticket_type']) === 'vip' ? 'selected' : ''; ?>>vip</option>
                                            <option value="regular" <?php echo strtolower($schedule['ticket_type']) === 'regular' ? 'selected' : ''; ?>>regular</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="seat-price">
                                            <?php
                                            // Fetch the seat price from the corresponding cinema table
                                            $price_table = htmlspecialchars($schedule['cinema']);
                                            $sql_price_display = "SELECT seat_price FROM `$price_table` LIMIT 1";
                                            $stmt_price_display = $mysqli->prepare($sql_price_display);
                                            
                                            // Check if statement preparation was successful
                                            if ($stmt_price_display === false) {
                                                die("Error preparing statement for table '$price_table': " . htmlspecialchars($mysqli->error));
                                            }

                                            $stmt_price_display->execute();
                                            $result_price_display = $stmt_price_display->get_result();
                                            $price_row = $result_price_display->fetch_assoc();

                                            if ($price_row) {
                                                $display_price = number_format((float)$price_row['seat_price'], 2, '.', '');
                                            } else {
                                                die("No prices found for table '$price_table'.");
                                            }
                                            ?>
                                            <span><?php echo htmlspecialchars($display_price); ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule['id']); ?>">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function updateSeatPrice(select) {
            const seatPriceDisplay = select.closest('tr').querySelector('.seat-price span');
            const ticketType = select.value;
            const seatPrice = ticketType === 'vip' ? '500.00' : '350.00'; // Set seat price based on ticket type
            seatPriceDisplay.textContent = seatPrice; // Update displayed seat price
        }

        function confirmUpdate() {
            return confirm("This will reset and update all data in the selected cinema. Do you want to continue?");
        }
    </script>
</body>
</html>