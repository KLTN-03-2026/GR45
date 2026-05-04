<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useClientStore } from "@/stores/clientStore.js";
import authApi from "@/api/authApi.js";

const router = useRouter();
const route = useRoute();
const clientStore = useClientStore();
const isScrolled = ref(false);
const isProfileMenuOpen = ref(false);
const isMobileMenuOpen = ref(false);
const profileMenuRef = ref(null);

const isLoggedIn = computed(() => clientStore.isLoggedIn);
const userName = computed(() => {
  const user = clientStore.user || {};
  const name =
    user.ho_va_ten ||
    user.ho_ten ||
    user.ten_khach_hang ||
    user.name ||
    (typeof user.email === "string" ? user.email.split("@")[0] : "");
  return name?.trim() || "Khách hàng";
});
const avatarLetter = computed(() => userName.value.charAt(0).toUpperCase());

// Kiểm tra route hiện tại để highlight menu
const isHomePage = computed(() => route.path === "/");

const handleScroll = () => {
  isScrolled.value = window.scrollY > 10;
};

const handleOutsideClick = (e) => {
  if (profileMenuRef.value && !profileMenuRef.value.contains(e.target)) {
    isProfileMenuOpen.value = false;
  }
};

const goLogin = () => {
  isMobileMenuOpen.value = false;
  router.push("/auth/login");
};

const goRegister = () => {
  isMobileMenuOpen.value = false;
  router.push("/auth/register");
};

const handleLogout = () => {
  isProfileMenuOpen.value = false;
  isMobileMenuOpen.value = false;
  clientStore.logout();
  router.push("/");
};

// Cuộn mượt đến section trên trang chủ
const scrollToSection = (sectionId) => {
  isMobileMenuOpen.value = false;
  if (route.path !== "/") {
    // Nếu không ở trang chủ, chuyển về trang chủ rồi cuộn
    router.push("/").then(() => {
      setTimeout(() => {
        const el = document.getElementById(sectionId);
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 300);
    });
  } else {
    const el = document.getElementById(sectionId);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
  }
};

onMounted(() => {
  window.addEventListener("scroll", handleScroll);
  document.addEventListener("click", handleOutsideClick);
  // Bổ sung profile khi đăng nhập nhưng store thiếu id hoặc thiếu tên hiển thị
  const u = clientStore.user || {};
  const hasDisplayName = !!(
    u.ho_va_ten ||
    u.ho_ten ||
    u.ten_khach_hang ||
    u.name
  );
  if (isLoggedIn.value && (!u.id || !hasDisplayName)) {
    clientApi
      .getProfile()
      .then((res) => {
        const p = res?.success ? res.data : res?.data || res?.khach_hang || res;
        if (p) clientStore.updateUser(p);
      })
      .catch(() => {});
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("scroll", handleScroll);
  document.removeEventListener("click", handleOutsideClick);
});
</script>

<template>
  <nav class="client-header" :class="{ 'client-header--scrolled': isScrolled }">
    <div class="client-header__inner">
      <!-- Logo -->
      <div class="client-header__logo" @click="router.push('/')">
        <span class="client-header__logo-icon material-symbols-outlined"
          >directions_bus</span
        >
        <span class="client-header__logo-text">BusSafe</span>
      </div>

      <!-- Menu desktop -->
      <div class="client-header__nav">
        <RouterLink
          to="/"
          class="client-header__nav-link"
          :class="{ 'client-header__nav-link--active': isHomePage }"
        >
          <span class="material-symbols-outlined client-header__nav-icon"
            >home</span
          >
          Trang chủ
        </RouterLink>
        <a
          href="javascript:void(0)"
          class="client-header__nav-link"
          @click="scrollToSection('tuyen-pho-bien')"
        >
          <span class="material-symbols-outlined client-header__nav-icon"
            >schedule</span
          >
          Lịch trình
        </a>
        <!-- <a
          href="javascript:void(0)"
          class="client-header__nav-link"
          @click="scrollToSection('tim-chuyen')"
        >
          <span class="material-symbols-outlined client-header__nav-icon"
            >search</span
          >
          Tìm chuyến
        </a> -->
        <RouterLink
          to="/hop-tac"
          class="client-header__nav-link"
          :class="{ 'client-header__nav-link--active': route.path === '/hop-tac' }"
        >
          <span class="material-symbols-outlined client-header__nav-icon"
            >handshake</span
          >
          Hợp tác với chúng tôi
        </RouterLink>
        <RouterLink
          to="/theo-doi-chuyen-xe"
          class="client-header__nav-link"
          :class="{
            'client-header__nav-link--active':
              route.path === '/theo-doi-chuyen-xe',
          }"
        >
          <span class="material-symbols-outlined client-header__nav-icon"
            >share_location</span
          >
          Theo dõi xe
        </RouterLink>
      </div>

      <!-- Khu vực bên phải: Đăng nhập / Profile -->
      <div class="client-header__actions">
        <!-- Chưa đăng nhập -->
        <template v-if="!isLoggedIn">
          <button @click="goLogin" class="client-header__btn-login">
            Đăng nhập
          </button>
          <button @click="goRegister" class="client-header__btn-register">
            Đăng ký
          </button>
        </template>

        <!-- Đã đăng nhập: hiển thị avatar + dropdown -->
        <template v-else>
          <div class="client-header__profile" ref="profileMenuRef">
            <button
              @click="isProfileMenuOpen = !isProfileMenuOpen"
              class="client-header__profile-trigger"
            >
              <div class="client-header__avatar">{{ avatarLetter }}</div>
              <span class="client-header__username">{{ userName }}</span>
              <span
                class="material-symbols-outlined client-header__chevron"
                :class="{ 'client-header__chevron--open': isProfileMenuOpen }"
                >expand_more</span
              >
            </button>

            <!-- Dropdown menu -->
            <Transition name="dropdown">
              <div v-show="isProfileMenuOpen" class="client-header__dropdown">
                <div class="client-header__dropdown-header">
                  <div class="client-header__dropdown-avatar">
                    {{ avatarLetter }}
                  </div>
                  <div class="client-header__dropdown-info">
                    <span class="client-header__dropdown-name">{{
                      userName
                    }}</span>
                    <span class="client-header__dropdown-role"
                      >Khách hàng thành viên</span
                    >
                  </div>
                </div>
                <div class="client-header__dropdown-divider"></div>
                <RouterLink
                  @click="isProfileMenuOpen = false"
                  to="/profile"
                  class="client-header__dropdown-item"
                >
                  <span class="material-symbols-outlined">person</span>
                  Thông tin cá nhân
                </RouterLink>
                <RouterLink
                  @click="isProfileMenuOpen = false"
                  to="/profile?tab=tickets"
                  class="client-header__dropdown-item"
                >
                  <span class="material-symbols-outlined">history</span>
                  Vé của tôi
                </RouterLink>
                <div class="client-header__dropdown-divider"></div>
                <button
                  @click="handleLogout"
                  class="client-header__dropdown-item client-header__dropdown-item--danger"
                >
                  <span class="material-symbols-outlined">logout</span>
                  Đăng xuất
                </button>
              </div>
            </Transition>
          </div>
        </template>

        <!-- Nút hamburger cho mobile -->
        <button
          class="client-header__hamburger"
          @click="isMobileMenuOpen = !isMobileMenuOpen"
          :class="{ 'client-header__hamburger--active': isMobileMenuOpen }"
        >
          <span class="client-header__hamburger-line"></span>
          <span class="client-header__hamburger-line"></span>
          <span class="client-header__hamburger-line"></span>
        </button>
      </div>
    </div>

    <!-- Menu mobile (slide-down) -->
    <Transition name="mobile-menu">
      <div v-show="isMobileMenuOpen" class="client-header__mobile-menu">
        <RouterLink
          to="/"
          class="client-header__mobile-link"
          :class="{ 'client-header__mobile-link--active': isHomePage }"
          @click="isMobileMenuOpen = false"
        >
          <span class="material-symbols-outlined">home</span>
          Trang chủ
        </RouterLink>
        <a
          href="javascript:void(0)"
          class="client-header__mobile-link"
          @click="scrollToSection('tuyen-pho-bien')"
        >
          <span class="material-symbols-outlined">schedule</span>
          Lịch trình
        </a>
        <a
          href="javascript:void(0)"
          class="client-header__mobile-link"
          @click="scrollToSection('tim-chuyen')"
        >
          <span class="material-symbols-outlined">search</span>
          Tìm chuyến
        </a>
        <RouterLink
          to="/hop-tac"
          class="client-header__mobile-link"
          :class="{ 'client-header__mobile-link--active': route.path === '/hop-tac' }"
          @click="isMobileMenuOpen = false"
        >
          <span class="material-symbols-outlined">handshake</span>
          Hợp tác với chúng tôi
        </RouterLink>
        <RouterLink
          to="/theo-doi-chuyen-xe"
          class="client-header__mobile-link"
          @click="isMobileMenuOpen = false"
        >
          <span class="material-symbols-outlined">share_location</span>
          Theo dõi xe
        </RouterLink>
        <div class="client-header__mobile-divider"></div>

        <!-- Nút đăng nhập / profile trong mobile menu -->
        <template v-if="!isLoggedIn">
          <button @click="goLogin" class="client-header__mobile-link">
            <span class="material-symbols-outlined">login</span>
            Đăng nhập
          </button>
          <button
            @click="goRegister"
            class="client-header__mobile-btn-register"
          >
            <span class="material-symbols-outlined">person_add</span>
            Đăng ký tài khoản
          </button>
        </template>
        <template v-else>
          <RouterLink
            to="/profile"
            class="client-header__mobile-link"
            @click="isMobileMenuOpen = false"
          >
            <span class="material-symbols-outlined">person</span>
            Thông tin cá nhân
          </RouterLink>
          <RouterLink
            to="/profile?tab=tickets"
            class="client-header__mobile-link"
            @click="isMobileMenuOpen = false"
          >
            <span class="material-symbols-outlined">history</span>
            Vé của tôi
          </RouterLink>
          <button
            @click="handleLogout"
            class="client-header__mobile-link client-header__mobile-link--danger"
          >
            <span class="material-symbols-outlined">logout</span>
            Đăng xuất
          </button>
        </template>
      </div>
    </Transition>
  </nav>
</template>

<style scoped>
/* ─── Header chính ─────────────────────────────────────── */
.client-header {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(20px) saturate(1.8);
  -webkit-backdrop-filter: blur(20px) saturate(1.8);
  border-bottom: 1px solid rgba(148, 163, 184, 0.1);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: "Manrope", sans-serif;
}

.client-header--scrolled {
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
}

.client-header__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 1.5rem;
  height: 68px;
}

/* ─── Logo ─────────────────────────────────────────────── */
.client-header__logo {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  cursor: pointer;
  user-select: none;
  transition: opacity 0.2s;
}

.client-header__logo:hover {
  opacity: 0.85;
}

.client-header__logo-icon {
  font-size: 28px;
  color: #1e40af;
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.client-header__logo-text {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ─── Navigation (Desktop) ─────────────────────────────── */
.client-header__nav {
  display: none;
  align-items: center;
  gap: 0.25rem;
}

@media (min-width: 768px) {
  .client-header__nav {
    display: flex;
  }
}

.client-header__nav-link {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem 0.85rem;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 700;
  color: #475569;
  text-decoration: none;
  transition: all 0.25s ease;
  position: relative;
  white-space: nowrap;
}

.client-header__nav-link:hover {
  color: #1e40af;
  background: rgba(59, 130, 246, 0.06);
}

.client-header__nav-link--active {
  color: #1e40af;
  background: rgba(59, 130, 246, 0.08);
  font-weight: 700;
}

.client-header__nav-link--active::after {
  content: "";
  position: absolute;
  bottom: -2px;
  left: 50%;
  transform: translateX(-50%);
  width: 20px;
  height: 3px;
  border-radius: 3px;
  background: linear-gradient(90deg, #3b82f6, #1e40af);
}

.client-header__nav-icon {
  font-size: 20px;
  opacity: 0.7;
}

.client-header__nav-link:hover .client-header__nav-icon,
.client-header__nav-link--active .client-header__nav-icon {
  opacity: 1;
}

/* ─── Khu vực hành động (phải) ─────────────────────────── */
.client-header__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ─── Nút Đăng nhập / Đăng ký ─────────────────────────── */
.client-header__btn-login {
  display: none;
  padding: 0.6rem 2rem;
  border: none;
  background: none;
  color: #1e40af;
  font-weight: 800;
  font-size: 1.1rem;
  min-width: 130px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.client-header__btn-login:hover {
  background: rgba(59, 130, 246, 0.06);
}

@media (min-width: 768px) {
  .client-header__btn-login {
    display: block;
  }
}

.client-header__btn-register {
  display: none;
  padding: 0.65rem 2.25rem;
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: #fff;
  border: none;
  font-weight: 800;
  font-size: 1.1rem;
  min-width: 140px;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
  font-family: inherit;
}

.client-header__btn-register:hover {
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  transform: translateY(-1px);
}

.client-header__btn-register:active {
  transform: scale(0.97);
}

@media (min-width: 768px) {
  .client-header__btn-register {
    display: block;
  }
}

/* ─── Profile Dropdown Trigger ─────────────────────────── */
.client-header__profile {
  position: relative;
}

.client-header__profile-trigger {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.3rem;
  padding-right: 0.75rem;
  border: 1px solid transparent;
  background: none;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.client-header__profile-trigger:hover {
  background: rgba(0, 0, 0, 0.03);
  border-color: rgba(0, 0, 0, 0.06);
}

.client-header__avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.client-header__username {
  font-weight: 700;
  font-size: 0.875rem;
  color: #334155;
  display: none;
}

@media (min-width: 640px) {
  .client-header__username {
    display: block;
  }
}

.client-header__chevron {
  font-size: 18px;
  color: #94a3b8;
  transition: transform 0.25s ease;
}

.client-header__chevron--open {
  transform: rotate(180deg);
}

/* ─── Dropdown Menu ────────────────────────────────────── */
.client-header__dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 240px;
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(20px);
  border-radius: 16px;
  box-shadow:
    0 10px 40px rgba(15, 23, 42, 0.12),
    0 0 0 1px rgba(148, 163, 184, 0.08);
  overflow: hidden;
  padding: 0.35rem 0;
}

.client-header__dropdown-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1rem 0.75rem;
}

.client-header__dropdown-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1rem;
  flex-shrink: 0;
}

.client-header__dropdown-info {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.client-header__dropdown-name {
  font-weight: 800;
  font-size: 0.9rem;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.client-header__dropdown-role {
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 500;
  margin-top: 0.1rem;
}

.client-header__dropdown-divider {
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(148, 163, 184, 0.15),
    transparent
  );
  margin: 0.25rem 0;
}

.client-header__dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  transition: all 0.15s ease;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  font-family: inherit;
}

.client-header__dropdown-item:hover {
  background: rgba(59, 130, 246, 0.05);
  color: #1e40af;
}

.client-header__dropdown-item .material-symbols-outlined {
  font-size: 20px;
  opacity: 0.7;
}

.client-header__dropdown-item--danger {
  color: #dc2626;
}

.client-header__dropdown-item--danger:hover {
  background: rgba(220, 38, 38, 0.05);
  color: #dc2626;
}

/* ─── Hamburger Menu (Mobile) ──────────────────────────── */
.client-header__hamburger {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  width: 38px;
  height: 38px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  border-radius: 10px;
  transition: background 0.2s;
}

.client-header__hamburger:hover {
  background: rgba(0, 0, 0, 0.04);
}

@media (min-width: 768px) {
  .client-header__hamburger {
    display: none;
  }
}

.client-header__hamburger-line {
  display: block;
  width: 20px;
  height: 2px;
  background: #334155;
  border-radius: 2px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  transform-origin: center;
}

/* Hiệu ứng X khi mở menu */
.client-header__hamburger--active .client-header__hamburger-line:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}

.client-header__hamburger--active .client-header__hamburger-line:nth-child(2) {
  opacity: 0;
  transform: scaleX(0);
}

.client-header__hamburger--active .client-header__hamburger-line:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

/* ─── Mobile Menu ──────────────────────────────────────── */
.client-header__mobile-menu {
  display: flex;
  flex-direction: column;
  padding: 0.5rem 1rem 1rem;
  border-top: 1px solid rgba(148, 163, 184, 0.1);
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(20px);
}

@media (min-width: 768px) {
  .client-header__mobile-menu {
    display: none;
  }
}

.client-header__mobile-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.85rem;
  font-size: 1rem;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  border-radius: 12px;
  transition: all 0.2s ease;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  font-family: inherit;
}

.client-header__mobile-link:hover,
.client-header__mobile-link:active {
  background: rgba(59, 130, 246, 0.05);
  color: #1e40af;
}

.client-header__mobile-link--active {
  color: #1e40af;
  background: rgba(59, 130, 246, 0.08);
  font-weight: 700;
}

.client-header__mobile-link .material-symbols-outlined {
  font-size: 22px;
  opacity: 0.7;
}

.client-header__mobile-link--danger {
  color: #dc2626;
}

.client-header__mobile-link--danger:hover {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.05);
}

.client-header__mobile-divider {
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(148, 163, 184, 0.15),
    transparent
  );
  margin: 0.35rem 0;
}

.client-header__mobile-btn-register {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  margin-top: 0.25rem;
  border: none;
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: #fff;
  font-weight: 700;
  font-size: 0.9rem;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);
  font-family: inherit;
  width: 100%;
}

.client-header__mobile-btn-register:hover {
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
}

.client-header__mobile-btn-register .material-symbols-outlined {
  font-size: 20px;
}

/* ─── Hiệu ứng Dropdown ───────────────────────────────── */
.dropdown-enter-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-leave-active {
  transition: all 0.15s ease-in;
}

.dropdown-enter-from {
  opacity: 0;
  transform: translateY(-8px) scale(0.97);
}

.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px) scale(0.98);
}

/* ─── Hiệu ứng Mobile Menu ────────────────────────────── */
.mobile-menu-enter-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.mobile-menu-leave-active {
  transition: all 0.2s ease-in;
}

.mobile-menu-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
