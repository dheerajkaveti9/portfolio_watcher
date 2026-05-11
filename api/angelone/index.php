<?php
echo "WORKS!";
?>
```

Then access:
```
http://localhost/portfolio_watcher/api/angelone/
```

or
```
http://localhost/portfolio_watcher/api/angelone/index.php
```

---

### Fix 4: Check folder permissions

Right-click on the `angelone` folder in Windows Explorer:
1. Right-click → Properties
2. Go to Security tab
3. Make sure "Users" has Read & Execute permissions

---

### Fix 5: Check Apache error logs

Open:
```
C:\xampp\apache\logs\error.log