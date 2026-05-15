import { StyleSheet, Text, View, TextInput, TouchableOpacity, ScrollView, Alert, ActivityIndicator } from "react-native";
import { useContext, useState, useEffect } from "react";
import { AuthContext } from "@/src/store/AuthContext";
import { useRouter, Stack } from "expo-router";
import { IconSymbol } from "@/src/components/ui/icon-symbol";
import clientApi from "@/src/services/client-api";
import Animated, { FadeInDown } from "react-native-reanimated";

export default function EditProfileScreen() {
  const { user, refreshUser } = useContext(AuthContext);
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({
    ho_va_ten: user?.ho_va_ten || user?.ten_khach_hang || "",
    so_dien_thoai: user?.so_dien_thoai || "",
    email: user?.email || "",
    ngay_sinh: user?.ngay_sinh ? user.ngay_sinh.split('T')[0] : "",
    dia_chi: user?.dia_chi || "",
  });

  const handleUpdate = async () => {
    if (!form.ho_va_ten || !form.so_dien_thoai) {
      Alert.alert("Thông báo", "Vui lòng nhập đầy đủ Họ tên và Số điện thoại.");
      return;
    }

    setLoading(true);
    try {
      const res = await clientApi.updateProfile(form);
      if (res.data.success) {
        await refreshUser();
        Alert.alert("Thành công", "Thông tin cá nhân đã được cập nhật.", [
          { text: "OK", onPress: () => router.back() }
        ]);
      } else {
        Alert.alert("Lỗi", res.data.message || "Cập nhật thất bại.");
      }
    } catch (error: any) {
      Alert.alert("Lỗi", error.response?.data?.message || "Đã có lỗi xảy ra.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container}>
      <Stack.Screen options={{ title: "Chỉnh sửa hồ sơ", headerShadowVisible: false }} />
      
      <Animated.View entering={FadeInDown.duration(600)} style={styles.form}>
        <InputGroup 
          label="Họ và tên" 
          value={form.ho_va_ten} 
          onChangeText={(text) => setForm({...form, ho_va_ten: text})} 
          placeholder="Nhập họ và tên"
          icon="person.fill"
        />

        <InputGroup 
          label="Số điện thoại" 
          value={form.so_dien_thoai} 
          onChangeText={(text) => setForm({...form, so_dien_thoai: text})} 
          placeholder="Nhập số điện thoại"
          keyboardType="phone-pad"
          icon="phone.fill"
        />

        <InputGroup 
          label="Email" 
          value={form.email} 
          onChangeText={(text) => setForm({...form, email: text})} 
          placeholder="Nhập email"
          keyboardType="email-address"
          icon="envelope.fill"
          editable={false} // Email thường không cho đổi tùy tiện
        />

        <InputGroup 
          label="Ngày sinh" 
          value={form.ngay_sinh} 
          onChangeText={(text) => setForm({...form, ngay_sinh: text})} 
          placeholder="YYYY-MM-DD"
          icon="calendar"
        />

        <InputGroup 
          label="Địa chỉ" 
          value={form.dia_chi} 
          onChangeText={(text) => setForm({...form, dia_chi: text})} 
          placeholder="Nhập địa chỉ"
          icon="house.fill"
          multiline
        />

        <TouchableOpacity 
          style={[styles.saveButton, loading && styles.disabledButton]} 
          onPress={handleUpdate}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.saveButtonText}>Lưu thay đổi</Text>
          )}
        </TouchableOpacity>
      </Animated.View>
    </ScrollView>
  );
}

function InputGroup({ label, value, onChangeText, placeholder, icon, keyboardType = "default", editable = true, multiline = false }: any) {
  return (
    <View style={styles.inputGroup}>
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.inputContainer, !editable && styles.readOnlyInput, multiline && styles.textAreaContainer]}>
        <View style={styles.iconContainer}>
          <IconSymbol name={icon} size={20} color="#94a3b8" />
        </View>
        <TextInput 
          style={[styles.input, multiline && styles.textArea]}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor="#94a3b8"
          keyboardType={keyboardType}
          editable={editable}
          multiline={multiline}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f8fafc" },
  form: { padding: 20 },
  inputGroup: { marginBottom: 20 },
  label: { fontSize: 14, fontWeight: "600", color: "#64748b", marginBottom: 8, marginLeft: 4 },
  inputContainer: { flexDirection: "row", alignItems: "center", backgroundColor: "#ffffff", borderRadius: 16, borderWidth: 1, borderColor: "#e2e8f0", paddingHorizontal: 12 },
  readOnlyInput: { backgroundColor: "#f1f5f9", borderColor: "#cbd5e1" },
  iconContainer: { marginRight: 12 },
  input: { flex: 1, height: 56, color: "#0f172a", fontSize: 16 },
  textAreaContainer: { alignItems: "flex-start", paddingTop: 12 },
  textArea: { height: 100, textAlignVertical: "top" },
  saveButton: { backgroundColor: "#0052cc", height: 56, borderRadius: 16, justifyContent: "center", alignItems: "center", marginTop: 20, shadowColor: "#0052cc", shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4 },
  saveButtonText: { color: "#ffffff", fontSize: 16, fontWeight: "bold" },
  disabledButton: { opacity: 0.7 },
});
