import { StyleSheet, Text, View, TextInput, TouchableOpacity, ScrollView, Alert, ActivityIndicator } from "react-native";
import { useState } from "react";
import { useRouter, Stack } from "expo-router";
import { IconSymbol } from "@/src/components/ui/icon-symbol";
import clientApi from "@/src/services/client-api";
import Animated, { FadeInDown } from "react-native-reanimated";

export default function ChangePasswordScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [showPasswords, setShowPasswords] = useState({
    cu: false,
    moi: false,
    conf: false
  });
  const [form, setForm] = useState({
    mat_khau_cu: "",
    mat_khau_moi: "",
    mat_khau_moi_confirmation: "",
  });

  const handleUpdate = async () => {
    if (!form.mat_khau_cu || !form.mat_khau_moi || !form.mat_khau_moi_confirmation) {
      Alert.alert("Thông báo", "Vui lòng nhập đầy đủ các trường mật khẩu.");
      return;
    }

    if (form.mat_khau_moi !== form.mat_khau_moi_confirmation) {
      Alert.alert("Lỗi", "Mật khẩu mới và xác nhận không khớp.");
      return;
    }

    setLoading(true);
    try {
      const res = await clientApi.changePassword(form);
      if (res.data.success) {
        Alert.alert("Thành công", "Mật khẩu đã được thay đổi.", [
          { text: "OK", onPress: () => router.back() }
        ]);
      } else {
        Alert.alert("Lỗi", res.data.message || "Đổi mật khẩu thất bại.");
      }
    } catch (error: any) {
      Alert.alert("Lỗi", error.response?.data?.message || "Đã có lỗi xảy ra. Kiểm tra lại mật khẩu cũ.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container}>
      <Stack.Screen options={{ title: "Đổi mật khẩu", headerShadowVisible: false }} />
      
      <Animated.View entering={FadeInDown.duration(600)} style={styles.form}>
        <View style={styles.infoBox}>
          <IconSymbol name="info.circle.fill" size={20} color="#3b82f6" />
          <Text style={styles.infoText}>Mật khẩu mới nên bao gồm ít nhất 8 ký tự, bao gồm chữ cái và chữ số để bảo mật hơn.</Text>
        </View>

        <PasswordInput 
          label="Mật khẩu hiện tại" 
          value={form.mat_khau_cu} 
          onChangeText={(text: string) => setForm({...form, mat_khau_cu: text})} 
          secureTextEntry={!showPasswords.cu}
          toggleSecure={() => setShowPasswords({...showPasswords, cu: !showPasswords.cu})}
        />

        <PasswordInput 
          label="Mật khẩu mới" 
          value={form.mat_khau_moi} 
          onChangeText={(text: string) => setForm({...form, mat_khau_moi: text})} 
          secureTextEntry={!showPasswords.moi}
          toggleSecure={() => setShowPasswords({...showPasswords, moi: !showPasswords.moi})}
        />

        <PasswordInput 
          label="Xác nhận mật khẩu mới" 
          value={form.mat_khau_moi_confirmation} 
          onChangeText={(text: string) => setForm({...form, mat_khau_moi_confirmation: text})} 
          secureTextEntry={!showPasswords.conf}
          toggleSecure={() => setShowPasswords({...showPasswords, conf: !showPasswords.conf})}
        />

        <TouchableOpacity 
          style={[styles.saveButton, loading && styles.disabledButton]} 
          onPress={handleUpdate}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.saveButtonText}>Cập nhật mật khẩu</Text>
          )}
        </TouchableOpacity>
      </Animated.View>
    </ScrollView>
  );
}

function PasswordInput({ label, value, onChangeText, secureTextEntry, toggleSecure }: any) {
  return (
    <View style={styles.inputGroup}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.inputContainer}>
        <View style={styles.iconContainer}>
          <IconSymbol name="lock.fill" size={20} color="#94a3b8" />
        </View>
        <TextInput 
          style={styles.input}
          value={value}
          onChangeText={onChangeText}
          placeholder="••••••••"
          placeholderTextColor="#94a3b8"
          secureTextEntry={secureTextEntry}
        />
        <TouchableOpacity onPress={toggleSecure} style={styles.eyeIcon}>
          <IconSymbol name={secureTextEntry ? "eye.fill" : "eye.slash.fill"} size={20} color="#94a3b8" />
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc" },
  form: { padding: 20 },
  infoBox: { flexDirection: "row", backgroundColor: "#eff6ff", padding: 16, borderRadius: 16, marginBottom: 24, alignItems: "center" },
  infoText: { flex: 1, marginLeft: 12, fontSize: 13, color: "#1e40af", lineHeight: 18 },
  inputGroup: { marginBottom: 20 },
  label: { fontSize: 14, fontWeight: "600", color: "#64748b", marginBottom: 8, marginLeft: 4 },
  inputContainer: { flexDirection: "row", alignItems: "center", backgroundColor: "#ffffff", borderRadius: 16, borderWidth: 1, borderColor: "#e2e8f0", paddingHorizontal: 12 },
  iconContainer: { marginRight: 12 },
  input: { flex: 1, height: 56, color: "#0f172a", fontSize: 16 },
  eyeIcon: { padding: 8 },
  saveButton: { backgroundColor: "#0052cc", height: 56, borderRadius: 16, justifyContent: "center", alignItems: "center", marginTop: 20, shadowColor: "#0052cc", shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4 },
  saveButtonText: { color: "#ffffff", fontSize: 16, fontWeight: "bold" },
  disabledButton: { opacity: 0.7 },
});
