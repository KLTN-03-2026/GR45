import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View, ScrollView, ActivityIndicator, TouchableOpacity, TextInput, Alert, KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter, Stack } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import Animated, { FadeInDown } from 'react-native-reanimated';
import clientApi from '@/src/services/client-api';

const RATING_CATEGORIES = [
  { key: 'diem_so', label: 'Đánh giá tổng thể' },
  { key: 'diem_dich_vu', label: 'Chất lượng dịch vụ' },
  { key: 'diem_an_toan', label: 'Độ an toàn' },
  { key: 'diem_sach_se', label: 'Độ sạch sẽ' },
  { key: 'diem_thai_do', label: 'Thái độ phục vụ' },
];

export default function RatingScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [ratings, setRatings] = useState<any>({
    diem_so: 5,
    diem_dich_vu: 5,
    diem_an_toan: 5,
    diem_sach_se: 5,
    diem_thai_do: 5,
  });
  const [comment, setComment] = useState("");

  useEffect(() => {
    if (id) {
      fetchTicketDetail();
    }
  }, [id]);

  const fetchTicketDetail = async () => {
    try {
      setLoading(true);
      const response = await clientApi.getTicketDetail(id as string);
      if (response.data.success) {
        setTicket(response.data.data);
      }
    } catch (error) {
      console.error("Lỗi lấy chi tiết vé:", error);
      Alert.alert("Lỗi", "Không thể tải thông tin vé.");
    } finally {
      setLoading(false);
    }
  };

  const handleRatingChange = (key: string, value: number) => {
    setRatings((prev: any) => ({ ...prev, [key]: value }));
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    try {
      const payload = {
        trip_id: ticket.chuyen_xe_id || ticket.chuyen_xe?.id,
        ma_ve_list: ticket.chi_tiet_ves?.map((ct: any) => ct.ma_ve) || [ticket.ma_ve],
        ...ratings,
        noi_dung: comment,
      };

      const response = await clientApi.submitRating(payload);
      if (response.data.success) {
        Alert.alert("Thành công", "Cảm ơn bạn đã gửi đánh giá!", [
          { text: "OK", onPress: () => router.back() }
        ]);
      } else {
        Alert.alert("Lỗi", response.data.message || "Gửi đánh giá thất bại.");
      }
    } catch (error: any) {
      Alert.alert("Lỗi", error.response?.data?.message || "Đã có lỗi xảy ra.");
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.centeredContainer}>
        <ActivityIndicator size="large" color="#0052cc" />
      </View>
    );
  }

  const chuyenXe = ticket?.chuyen_xe || ticket?.chuyenXe || {};
  const tuyenDuong = chuyenXe.tuyen_duong || chuyenXe.tuyenDuong || {};

  return (
    <SafeAreaView style={styles.container}>
      <Stack.Screen options={{ title: "Đánh giá chuyến đi", headerShadowVisible: false }} />
      <KeyboardAvoidingView 
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        style={{ flex: 1 }}
      >
        <ScrollView contentContainerStyle={styles.scrollContainer} showsVerticalScrollIndicator={false}>
          <Animated.View entering={FadeInDown.duration(500)} style={styles.tripSummary}>
            <Text style={styles.summaryTitle}>Thông tin chuyến đi</Text>
            <View style={styles.routeBox}>
              <Text style={styles.routeName}>{tuyenDuong.diem_bat_dau} → {tuyenDuong.diem_ket_thuc}</Text>
              <Text style={styles.routeMeta}>{chuyenXe.ngay_khoi_hanh} · {chuyenXe.gio_khoi_hanh?.slice(0, 5)}</Text>
              <Text style={styles.operatorName}>{tuyenDuong.nha_xe?.ten_nha_xe || "Nhà xe"}</Text>
            </View>
          </Animated.View>

          <View style={styles.ratingSection}>
            {RATING_CATEGORIES.map((cat) => (
              <View key={cat.key} style={styles.ratingRow}>
                <Text style={styles.ratingLabel}>{cat.label}</Text>
                <View style={styles.starsContainer}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <TouchableOpacity 
                      key={star} 
                      onPress={() => handleRatingChange(cat.key, star)}
                      activeOpacity={0.7}
                    >
                      <Ionicons 
                        name={ratings[cat.key] >= star ? "star" : "star-outline"} 
                        size={32} 
                        color={ratings[cat.key] >= star ? "#f59e0b" : "#cbd5e1"} 
                        style={styles.starIcon}
                      />
                    </TouchableOpacity>
                  ))}
                </View>
              </View>
            ))}
          </View>

          <View style={styles.commentSection}>
            <Text style={styles.sectionLabel}>Nhận xét của bạn (tùy chọn)</Text>
            <TextInput
              style={styles.commentInput}
              placeholder="Chia sẻ trải nghiệm của bạn về chuyến đi này..."
              multiline
              numberOfLines={4}
              value={comment}
              onChangeText={setComment}
              textAlignVertical="top"
            />
          </View>

          <TouchableOpacity 
            style={[styles.submitBtn, submitting && styles.disabledBtn]} 
            onPress={handleSubmit}
            disabled={submitting}
          >
            {submitting ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitBtnText}>Gửi đánh giá</Text>
            )}
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#ffffff' },
  centeredContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  scrollContainer: { padding: 20 },
  tripSummary: { marginBottom: 24, padding: 16, backgroundColor: '#f8fafc', borderRadius: 16, borderWidth: 1, borderColor: '#e2e8f0' },
  summaryTitle: { fontSize: 14, fontWeight: '600', color: '#64748b', marginBottom: 8 },
  routeBox: { },
  routeName: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', marginBottom: 4 },
  routeMeta: { fontSize: 13, color: '#64748b', marginBottom: 4 },
  operatorName: { fontSize: 14, color: '#0052cc', fontWeight: '600' },
  
  ratingSection: { marginBottom: 24 },
  ratingRow: { marginBottom: 20 },
  ratingLabel: { fontSize: 15, fontWeight: '600', color: '#334155', marginBottom: 8 },
  starsContainer: { flexDirection: 'row', alignItems: 'center' },
  starIcon: { marginRight: 8 },
  
  commentSection: { marginBottom: 32 },
  sectionLabel: { fontSize: 15, fontWeight: '600', color: '#334155', marginBottom: 12 },
  commentInput: { backgroundColor: '#f8fafc', borderRadius: 16, borderWidth: 1, borderColor: '#e2e8f0', padding: 16, height: 120, fontSize: 16, color: '#1e293b' },
  
  submitBtn: { backgroundColor: '#0052cc', height: 56, borderRadius: 16, justifyContent: 'center', alignItems: 'center', shadowColor: '#0052cc', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4 },
  submitBtnText: { color: '#ffffff', fontSize: 16, fontWeight: 'bold' },
  disabledBtn: { opacity: 0.7 },
});
