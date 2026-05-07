# Cau truc du an my-app

Tai lieu nay ghi lai cau truc hien tai va y nghia cua tung thu muc/file de bat dau du an theo huong mo rong.

## 1) Cay thu muc (rut gon)

```text
my-app/
├── app/                         # Expo Router entry (khai bao route man hinh)
│   ├── _layout.tsx              # Root Stack layout
│   └── (tabs)/
│       ├── _layout.tsx          # Tab layout
│       └── index.tsx            # Man hinh dang nhap tam thoi
├── assets/
│   └── images/                  # Icon/splash/favicons cho Expo
├── src/                         # Khu vuc code chinh theo clean structure
│   ├── assets/                  # Tai nguyen tinh cua app (anh, icon, font)
│   ├── components/              # Reusable UI components (dumb/presentational)
│   │   ├── auth/
│   │   │   └── auth-input.tsx
│   │   ├── haptic-tab.tsx
│   │   └── ui/
│   │       ├── icon-symbol.tsx
│   │       └── icon-symbol.ios.tsx
│   ├── constants/               # Hang so, theme, config
│   │   └── theme.ts
│   ├── hooks/                   # Custom hooks dung chung
│   │   ├── use-color-scheme.ts
│   │   └── use-color-scheme.web.ts
│   ├── navigation/              # Cac helper/cau hinh dieu huong (du phong)
│   ├── screens/                 # Smart components/man hinh theo domain
│   ├── services/                # API client + service goi backend
│   ├── store/                   # Quan ly state toan cuc
│   └── utils/                   # Ham helper/formatter
├── App.js                       # Entry point (load expo-router)
├── app.json                     # Cau hinh Expo
├── package.json                 # Scripts + dependencies
└── tsconfig.json                # TypeScript config + path alias
```

## 2) Nguyen tac to chuc code

- app/ chi giu route layer: khai bao luong man hinh voi Expo Router.
- src/ la noi viet logic chinh cua du an.
- Man hinh that su nen dat trong src/screens, app/ chi import va render lai.
- Moi call API dat trong src/services, khong viet truc tiep trong UI component.
- Shared state dat trong src/store (Zustand/Redux), tranh truyen props qua nhieu tang.
- Ham xu ly du lieu (format date, currency, parser...) dat trong src/utils.

## 3) Ghi chu cho giai doan khoi tao

- Dang dung Expo Router, vi vay khong can tao AppNavigator thu cong ngay tu dau.
- Da co alias @/\* trong tsconfig, co the import theo dang: @/src/components/...
- assets/images dang giu icon/splash bat buoc cho Expo, khong nen xoa cac file nay tru khi thay the trong app.json.
- Neu muon chuan hoa hon nua, tao file barrel index.ts cho tung folder (components, services, hooks).

## 4) Ke hoach mo rong de nghi

1. Tao src/services/api-client.ts (axios/fetch wrapper + error handling).
2. Tao src/services/auth.service.ts cho login/register.
3. Tao src/store/auth-store.ts luu session token va profile.
4. Tach LoginScreen thanh src/screens/auth/LoginScreen.tsx.
5. app/(tabs)/index.tsx chi con vai tro route wrapper render LoginScreen.
