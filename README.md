# HBAssistant-php
用于黄白助手的音乐接口php

Cookie抓取方法：
Chrome浏览器登录QQ音乐&网易云音乐后，F12 网络-找请求头-cookie整段添加

更新：wyy* 优先使用更现代的 cloudsearch 接口。  

  备选逻辑： 如果 cloudsearch 失败，会自动回退到旧版 web 接口。这种“双保险”极大提高了搜索成功率。  
  虑到 cloudsearch 和 web 接口返回的字段名不同（例如歌手字段可能是 ar 或 artists，专辑可能是 al 或 album），V2 增加了逻辑判断来提取这些信息，避免因字段名变更导致报错。  
