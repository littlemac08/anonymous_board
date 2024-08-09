<?php
    require_once 'vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $servername = "localhost";
    $username = "root";
    $password = "mac1234!";  // MySQL root 비밀번호
    $dbname = "anonymous_board";
    $super_password = $_ENV['supersecret']; // 슈퍼 계정 비밀번호

    // 데이터베이스 연결
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 연결 확인
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }



    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id']) && isset($_POST['password'])) {
        $post_id = $_POST['post_id'];
        $password = $_POST['password'];
    
        // 비밀번호 확인
        $stmt = $conn->prepare("SELECT password FROM posts WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
    
        // 비밀번호가 일치하거나 슈퍼 계정 비밀번호가 입력되었는지 확인
        if (password_verify($password, $hashed_password) || $password === $super_password) {
            if ($_POST['action'] == '수정') {
                $new_content = $_POST['new_content'];
                // SQL 쿼리 준비 및 실행 (게시물 수정)
                $stmt = $conn->prepare("UPDATE posts SET content = ? WHERE id = ?");
                $stmt->bind_param("si", $new_content, $post_id);
                $stmt->execute();
                $stmt->close();
            } elseif ($_POST['action'] == '삭제') {
                // SQL 쿼리 준비 및 실행 (게시물 삭제)
                $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->bind_param("i", $post_id);
                $stmt->execute();
                $stmt->close();
            }
    
            // 리다이렉트 (PRG 패턴)
            header("Location: index.php");
            exit();
        } else {
            echo "<p>비밀번호가 일치하지 않습니다.</p>";
        }
    }
    

    // 게시물 저장 요청 처리
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post']) && isset($_POST['password'])) {
        $post = $_POST['post'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // 비밀번호 해시화

        // SQL 쿼리 준비 및 실행
        $stmt = $conn->prepare("INSERT INTO posts (content, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $post, $password);
        $stmt->execute();
        $stmt->close();

        // 리다이렉트 (PRG 패턴)
        header("Location: index.php");
        exit();
    }

    // 게시물 가져오기 (기존 코드)
    $result = $conn->query("SELECT * FROM posts ORDER BY created_at DESC");

    if ($result->num_rows > 0) {
        // 결과를 출력
        while($row = $result->fetch_assoc()) {
            echo "<div>";
            echo "<p>" . htmlspecialchars($row['content']) . "</p>";
            echo "<small>게시일: " . $row['created_at'] . "</small>";

            // 수정 및 삭제를 위한 폼 통합
            echo "<form method='post' action='index.php'>";
            echo "<input type='hidden' name='post_id' value='" . $row['id'] . "'>";
            echo "<textarea name='new_content' cols='30' rows='3'>" . htmlspecialchars($row['content']) . "</textarea><br>";
            echo "<label for='password'>비밀번호:</label>";
            echo "<input type='password' name='password' required><br>";
            echo "<input type='submit' name='action' value='수정'>";
            echo "<input type='submit' name='action' value='삭제'>";
            echo "</form>";


            echo "<hr>";
            echo "</div>";
        }
    } else {
        echo "<p>게시물이 없습니다.</p>";
    }

    // 데이터베이스 연결 종료 (마지막에 실행)
    $conn->close();
?>


<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>익명 게시판</title>
</head>
<body>
    <h1>익명 게시판</h1>
    <form action="index.php" method="post">
        <label for="post">게시물:</label><br>
        <textarea name="post" id="post" cols="30" rows="10"></textarea><br><br>
        <label for="password">비밀번호:</label><br>
        <input type="password" name="password" id="password" required><br><br>
        <input type="submit" value="게시하기">
    </form>

</body>
</html>