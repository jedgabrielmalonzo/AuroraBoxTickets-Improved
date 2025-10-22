<?php
$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ? OR parent_id = ? ORDER BY created_at ASC");
$stmt->bind_param("ii", $id, $id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  echo "<div class='p-2 mb-2 border rounded'>
          <b>{$row['sender_type']}:</b> {$row['body']}
          <div class='text-muted small'>".date("M d, Y h:i A", strtotime($row['created_at']))."</div>
        </div>";
}
?>

<form method="POST" action="send_reply.php">
  <input type="hidden" name="parent_id" value="<?= $id ?>">
  <textarea name="body" rows="3" class="form-control" placeholder="Reply..." required></textarea>
  <button class="btn btn-success mt-2">Reply</button>
</form>