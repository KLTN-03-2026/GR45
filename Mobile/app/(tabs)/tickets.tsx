import { StyleSheet, Text, View, TouchableOpacity, Image } from "react-native";
import { useContext } from "react";
import { AuthContext } from "@/src/store/AuthContext";
import { useRouter } from "expo-router";

export default function TicketsScreen() {
  const { user } = useContext(AuthContext);
  const router = useRouter();

  if (!user) {
    return (
      <View style={styles.container}>
        <Text style={styles.message}>Đăng nhập để xem và quản lý những chuyến đi của bạn dễ dàng hơn</Text>
        <TouchableOpacity 
          style={styles.loginButton} 
          onPress={() => router.push("/login")}
        >
          <Text style={styles.loginText}>Đăng nhập</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Vé Của Tôi</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: "center", alignItems: "center", backgroundColor: "#f8fafc", padding: 20 },
  message: { fontSize: 16, color: "#64748b", marginBottom: 24, textAlign: "center", lineHeight: 24 },
  loginButton: { backgroundColor: "#0052cc", paddingVertical: 14, paddingHorizontal: 32, borderRadius: 8, elevation: 2, shadowColor: "#0052cc", shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.2, shadowRadius: 4 },
  loginText: { color: "#ffffff", fontSize: 16, fontWeight: "600" },
  title: { fontSize: 20, fontWeight: "bold", marginBottom: 12, color: "#0f172a" },
});
