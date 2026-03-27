<style id="rbj-user-main-nav-base">
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 50px;
  background: rgba(0,0,0,0.8);
  z-index: 999;
}

.navbar .logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: white;
  text-decoration: none;
  font-size: 22px;
  font-weight: 700;
}

.navbar .logo img {
  height: 60px;
  width: auto;
  background: transparent;
}

.navbar .nav-links {
  display: flex;
  align-items: center;
  gap: 15px;
}

.navbar .nav-links a {
  color: white;
  text-decoration: none;
  font-weight: 500;
  margin-left: 15px;
}

.navbar .nav-links a:hover {
  text-decoration: underline;
}

.account-dropdown {
  position: relative;
  display: flex;
  align-items: center;
  margin-left: 15px;
}

.account-icon {
  width: 40px;
  height: 40px;
  background: #27ae60;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: bold;
}

.account-username {
  font-weight: 600;
  margin-left: 5px;
  color: white;
}

.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.account-trigger i {
  font-size: 18px;
}

.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: #1e1e1e;
  border-radius: 10px;
  min-width: 200px;
  padding: 8px 0;
  display: none;
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  z-index: 999;
}

.account-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  color: white;
  text-decoration: none;
  font-size: 14px;
}

.account-menu a:hover {
  background: rgba(255,255,255,0.08);
}

.account-menu i {
  font-size: 18px;
}

.account-dropdown.active .account-menu {
  display: block;
}

.account-menu {
  pointer-events: auto;
  z-index: 9999;
}

.account-trigger {
  pointer-events: auto;
}

@media (max-width: 768px) {
  .navbar {
    padding: 10px 20px;
  }

  .navbar .nav-links a {
    margin-left: 10px;
    font-size: 14px;
  }
}
</style>
