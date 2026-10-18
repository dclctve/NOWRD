<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['name'=>'NOWRD Content API','version'=>'1','endpoints'=>['GET api/v1/posts.php?page=1&limit=10&lang=id','GET api/v1/post.php?slug=your-article-slug&lang=id','GET api/v1/categories.php'],'documentation'=>'See API-V1.md'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
