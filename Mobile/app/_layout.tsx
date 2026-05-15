import {
  DarkTheme,
  DefaultTheme,
  ThemeProvider,
} from "@react-navigation/native";
import { Stack, useRouter, useSegments } from "expo-router";
import { StatusBar } from "expo-status-bar";
import "react-native-reanimated";
import { useEffect, useContext } from "react";
import { View, ActivityIndicator } from "react-native";

import { useColorScheme } from "@/src/hooks/use-color-scheme";
import { AuthProvider, AuthContext } from "@/src/store/AuthContext";

export const unstable_settings = {
  anchor: "(tabs)",
};

function RootLayoutNav() {
  const colorScheme = useColorScheme();
  const { user, isLoading } = useContext(AuthContext);
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    // Không còn bắt buộc đăng nhập ở ngoại vi (cho phép khách xem tab tìm chuyến)
    // Chỉ cần chặn user đã đăng nhập mà vẫn lọt vào trang login:
    if (isLoading) return;
    
    const isLoginRoute = segments[0] === "login";
    if (user && isLoginRoute) {
      router.replace("/(tabs)");
    }
  }, [user, isLoading, segments]);

  if (isLoading) {
    // Hiển thị màn hình chờ trong lúc checkToken (khi vừa mở app lên)
    return (
      <View style={{ flex: 1, backgroundColor: "#ffffff", justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" color="#0052cc" />
      </View>
    );
  }

  return (
    <ThemeProvider value={colorScheme === "dark" ? DarkTheme : DefaultTheme}>
      <Stack>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ headerShown: false }} />
        <Stack.Screen name="tracking" options={{ headerShown: false }} />
        <Stack.Screen name="vouchers/hunt" options={{ presentation: 'card' }} />
        <Stack.Screen name="vouchers/my-vouchers" options={{ presentation: 'card' }} />
      </Stack>
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <RootLayoutNav />
    </AuthProvider>
  );
}
