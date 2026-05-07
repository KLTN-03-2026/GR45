import { StyleSheet, Text, View, TouchableOpacity } from "react-native";
import { useContext } from "react";
import { AuthContext } from "@/src/store/AuthContext";
import { useRouter } from "expo-router";

export default function ProfileScreen() {
  const { user } = useContext(AuthContext);
  const router = useRouter();

  if (!user) {
    return (
      <View style={styles.container}>
        <Text style={styles.message}>Vui lòng đăng nhập để xem thông tin tài khoản.</Text>
        <TouchableOpacity 
          style={styles.loginButton} 
          onPress={() => router.push("/login")}
        >
          <Text style={styles.loginText}>Đăng nhập ngay</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Xin chào, {user.ten_khach_hang || user.email || "Khách Hàng"}!</Text>
      <Text>Profile / Tài Khoản</Text>
      {/* Nút Đăng xuất có thể đặt ở đây sau */}
    </View>
  );
}
const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: "center", alignItems: "center", backgroundColor: "#f8fafc", padding: 20 },
  message: { fontSize: 16, color: "#64748b", marginBottom: 20, textAlign: "center" },
  loginButton: { backgroundColor: "#0052cc", paddingVertical: 12, paddingHorizontal: 32, borderRadius: 8 },
  loginText: { color: "#ffffff", fontSize: 16, fontWeight: "600" },
  title: { fontSize: 20, fontWeight: "bold", marginBottom: 12, color: "#0f172a" },
});
