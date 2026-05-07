import React, { useState } from "react";
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { useRouter } from "expo-router";
import { useContext } from "react";
import { AuthContext } from "../src/store/AuthContext";
import { SafeAreaView } from "react-native-safe-area-context";

export default function LoginScreen() {
  const [emailOrPhone, setEmailOrPhone] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  
  const { signIn } = useContext(AuthContext);

  const handleLogin = async () => {
    if (!emailOrPhone || !password) {
      Alert.alert("Lỗi", "Vui lòng nhập Email/Số điện thoại và Mật khẩu!");
      return;
    }

    try {
      setLoading(true);
      // Giả sử API nhận email hoặc số điện thoại vào trường "email" cho đơn giản
      const requestData = {
        email: emailOrPhone, 
        password: password,
      };

      // Đã dùng Context thay thế api trực tiếp
      await signIn(requestData);

      Alert.alert("Thành công", "Đăng nhập thành công!");
      router.replace("/(tabs)");
    } catch (error: any) {
      console.log("Lỗi đăng nhập qua Context:", error);
      Alert.alert("Đăng nhập thất bại", error.message || "Tài khoản hoặc mật khẩu không chính xác.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView 
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        style={styles.container}
      >
        <View style={styles.content}>
          <View style={styles.header}>
            <Text style={styles.title}>Xin chào!</Text>
            <Text style={styles.subtitle}>Đăng nhập để tiếp tục đặt vé chuyên xe</Text>
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Email / Số điện thoại</Text>
            <TextInput
              style={styles.input}
              placeholder="Nhập email hoặc số điện thoại"
              value={emailOrPhone}
              onChangeText={setEmailOrPhone}
              autoCapitalize="none"
              keyboardType="email-address"
              placeholderTextColor="#94a3b8"
            />
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Mật khẩu</Text>
            <TextInput
              style={styles.input}
              placeholder="Nhập mật khẩu"
              secureTextEntry
              value={password}
              onChangeText={setPassword}
              placeholderTextColor="#94a3b8"
            />
          </View>

          <TouchableOpacity style={styles.forgotPassword}>
            <Text style={styles.forgotPasswordText}>Quên mật khẩu?</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.loginButton}
            onPress={handleLogin}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <Text style={styles.loginText}>Đăng Nhập</Text>
            )}
          </TouchableOpacity>

          <View style={styles.registerContainer}>
            <Text style={styles.registerText}>Bạn chưa có tài khoản? </Text>
            <TouchableOpacity>
              <Text style={styles.registerLink}>Đăng ký ngay</Text>
            </TouchableOpacity>
          </View>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#ffffff",
  },
  container: {
    flex: 1,
  },
  content: {
    flex: 1,
    paddingHorizontal: 24,
    justifyContent: "center",
  },
  header: {
    marginBottom: 40,
  },
  title: {
    fontSize: 28,
    fontWeight: "bold",
    color: "#0f172a",
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: "#64748b",
  },
  formGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: "600",
    color: "#334155",
    marginBottom: 8,
  },
  input: {
    height: 52,
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingHorizontal: 16,
    fontSize: 16,
    color: "#0f172a",
    borderWidth: 1,
    borderColor: "#e2e8f0",
  },
  forgotPassword: {
    alignSelf: "flex-end",
    marginBottom: 24,
  },
  forgotPasswordText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#0052cc",
  },
  loginButton: {
    backgroundColor: "#0052cc",
    height: 56,
    borderRadius: 12,
    justifyContent: "center",
    alignItems: "center",
    shadowColor: "#0052cc",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 4,
  },
  loginText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "bold",
  },
  registerContainer: {
    flexDirection: "row",
    justifyContent: "center",
    marginTop: 24,
  },
  registerText: {
    fontSize: 14,
    color: "#64748b",
  },
  registerLink: {
    fontSize: 14,
    fontWeight: "bold",
    color: "#0052cc",
  },
});