<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 - Restricted</title>

<link href="https://fonts.googleapis.com/css?family=Fira+Code&display=swap" rel="stylesheet">

<style>
* {
  margin: 0;
  padding: 0;
  font-family: "Fira Code", monospace;
}

body {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100vh;
  background-color: #ecf0f1;
}

.container {
  text-align: center;
  margin: auto;
  padding: 4em;
}

.container img {
  width: 256px;
  height: 225px;
}

.container h1 {
  margin-top: 1rem;
  font-size: 35px;
}

.container h1 span {
  font-size: 60px;
}

.container p {
  margin-top: 1rem;
}

.container p.info {
  margin-top: 4em;
  font-size: 12px;
}

.container p.info a {
  text-decoration: none;
  color: rgb(84, 84, 206);
}

.back-btn {
  margin-top: 2rem;
  display: inline-block;
  padding: 10px 20px;
  background-color: #2c3e50;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  transition: 0.3s ease;
}

.back-btn:hover {
  background-color: #34495e;
}
</style>
</head>

<body>

<div class="container">
  <img src="https://i.imgur.com/qIufhof.png" alt="403 Image" />

  <h1>
    <span>403</span><br>
    Restricted Page
  </h1>

  <p>Only superadmin can access this page.</p>

  <a href="/billuser" class="back-btn">⬅ Back to Billuser</a>

  <p class="info">
    contact
    <a href="https://www.github.com/Frost-bit-star" target="_blank">
      Frost-bit-star
    </a>
  </p>
</div>

</body>
</html>
