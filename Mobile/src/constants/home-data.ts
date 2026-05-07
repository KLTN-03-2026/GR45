export type QuickAction = {
  id: string;
  title: string;
  subtitle: string;
  icon: string;
  color: string;
  bgColor: string;
};

export type PopularRoute = {
  id: string;
  from: string;
  to: string;
  duration: string;
  startPrice: string;
  image: string; // Use a URL or require
};

export const quickActions: QuickAction[] = [
  {
    id: "qa-1",
    title: "OMNIPULSE",
    subtitle: "Giám sát sinh trắc học",
    icon: "trending-up",
    color: "#1d4ed8",
    bgColor: "#eff6ff",
  },
  {
    id: "qa-2",
    title: "LIVE TRACKING",
    subtitle: "Lộ trình thời gian thực",
    icon: "timeline",
    color: "#15803d",
    bgColor: "#f0fdf4",
  },
  {
    id: "qa-3",
    title: "SOS KHẨN CẤP",
    subtitle: "Hỗ trợ 24/7 tức thì",
    icon: "report-problem",
    color: "#b91c1c",
    bgColor: "#fef2f2",
  },
  {
    id: "qa-4",
    title: "TRỢ LÝ AI",
    subtitle: "Hỏi đáp đặt vé tự động",
    icon: "smart-toy",
    color: "#0369a1",
    bgColor: "#f0f9ff",
  },
];

export const popularRoutes: PopularRoute[] = [
  {
    id: "route-1",
    from: "Sài Gòn",
    to: "Đà Lạt",
    duration: "6 giờ 30 phút",
    startPrice: "280.000đ",
    image:
      "https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&q=80&w=400&h=250",
  },
  {
    id: "route-2",
    from: "Sài Gòn",
    to: "Nha Trang",
    duration: "8 giờ 15 phút",
    startPrice: "350.000đ",
    image:
      "https://images.unsplash.com/photo-1583417319070-4a69db38a482?auto=format&fit=crop&q=80&w=400&h=250",
  },
];
