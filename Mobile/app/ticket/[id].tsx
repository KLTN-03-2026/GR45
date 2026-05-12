import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View, ScrollView, ActivityIndicator, TouchableOpacity, StatusBar, Alert, Share, Image } from 'react-native';
import { createEcho } from '@/src/utils/echo';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter, Stack } from 'expo-router';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import Animated, { FadeInDown, FadeInUp } from 'react-native-reanimated';
import QRCode from 'react-native-qrcode-svg';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';
import clientApi from '@/src/services/client-api';

export default function TicketDetailScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);

  useEffect(() => {
    if (id) {
      fetchTicketDetail();
    }
  }, [id]);

  // Realtime Payment Listener hook
  useEffect(() => {
     if (ticket?.ma_ve && ticket?.tinh_trang === 'dang_cho') {
        const echo = createEcho();
        if (!echo) return;

        console.log(`[DETAIL ECHO] Lắng nghe thanh toán cho vé ${ticket.ma_ve}`);
        try {
           echo.channel(`ve.${ticket.ma_ve}`)
              .listen('.ve.da_thanh_toan', (e: any) => {
                 console.log("[DETAIL ECHO] ✅ Thanh toán thành công qua realtime!", e);
                 Alert.alert("Thanh toán thành công 🎉", "Hệ thống đã xác nhận giao dịch của bạn.");
                 // Cập nhật nóng UI
                 setTicket((prev: any) => ({ ...prev, tinh_trang: 'da_thanh_toan' }));
              })
              .listen('.ve.huy_tu_dong', (e: any) => {
                 console.log("[DETAIL ECHO] ❌ Đã tự động hủy qua realtime", e);
                 setTicket((prev: any) => ({ ...prev, tinh_trang: 'huy' }));
              });
        } catch (err) {
           console.error("[DETAIL ECHO] Lỗi sub:", err);
        }

        return () => {
           console.log(`[DETAIL ECHO] Ngắt kết nối lắng nghe vé ${ticket.ma_ve}`);
           echo.leaveChannel(`ve.${ticket.ma_ve}`);
        };
     }
  }, [ticket?.ma_ve, ticket?.tinh_trang]);

  const fetchTicketDetail = async () => {
    try {
      setLoading(true);
      const response = await clientApi.getTicketDetail(id as string);
      if (response.data.success) {
        setTicket(response.data.data);
      }
    } catch (error) {
      console.error("Lỗi lấy chi tiết vé:", error);
      Alert.alert("Lỗi", "Không thể tải thông tin chi tiết vé. Vui lòng thử lại sau.");
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    Alert.alert(
      "Xác nhận hủy",
      "Bạn có chắc chắn muốn hủy vé này không?",
      [
        { text: "Hủy thao tác", style: "cancel" },
        { 
          text: "Đồng ý Hủy vé", 
          style: "destructive",
          onPress: async () => {
            try {
               setCancelling(true);
               const response = await clientApi.cancelTicket(id as string);
               if (response.data.success) {
                  Alert.alert("Thành công", "Đã gửi yêu cầu hủy vé thành công.");
                  fetchTicketDetail(); // refresh data
               }
            } catch (err: any) {
               Alert.alert("Lỗi", err.response?.data?.message || "Không thể hủy vé, vui lòng liên hệ hỗ trợ.");
            } finally {
               setCancelling(false);
            }
          } 
        }
      ]
    );
  };

  const shareTicket = async () => {
    try {
      if (!ticket) return;
      const cx = ticket.chuyen_xe || ticket.chuyenXe || {};
      const td = cx.tuyen_duong || cx.tuyenDuong || {};
      const message = `Mã vé xe của tôi tại GoBus là: ${ticket.ma_ve}. Chuyến ${td.diem_bat_dau || ''} -> ${td.diem_ket_thuc || ''}`;
      await Share.share({
        message,
        title: "Chia sẻ mã vé"
      });
    } catch (e) {}
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
  };

  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'da_thanh_toan':
        return { bg: '#10b981', text: 'ĐÃ THANH TOÁN', icon: 'checkmark-circle' };
      case 'dang_cho':
        return { bg: '#f59e0b', text: 'CHỜ THANH TOÁN', icon: 'time' };
      case 'huy':
      case 'da_huy':
        return { bg: '#ef4444', text: 'ĐÃ HỦY', icon: 'close-circle' };
      default:
        return { bg: '#6b7280', text: (status || 'KHÔNG XÁC ĐỊNH').toUpperCase(), icon: 'help-circle' };
    }
  };

  if (loading) {
    return (
      <View style={styles.centeredContainer}>
        <ActivityIndicator size="large" color="#0052cc" />
        <Text style={{ marginTop: 16, color: '#64748b' }}>Đang tải vé...</Text>
      </View>
    );
  }

  if (!ticket) {
    return (
      <View style={styles.centeredContainer}>
        <Ionicons name="alert-circle-outline" size={64} color="#94a3b8" />
        <Text style={{ fontSize: 18, fontWeight: '600', marginVertical: 12 }}>Không tìm thấy vé</Text>
        <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
          <Text style={{ color: '#ffffff', fontWeight: 'bold' }}>Quay lại</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const chuyenXe = ticket.chuyen_xe || ticket.chuyenXe || {};
  const tuyenDuong = chuyenXe.tuyen_duong || chuyenXe.tuyenDuong || {};
  const nhaXe = tuyenDuong.nha_xe || tuyenDuong.nhaXe || {};
  const detailList = ticket.chi_tiet_ves || ticket.chiTietVes || [];
  const listGhe = detailList.map((ct: any) => ct.ghe?.ma_ghe).join(', ') || 'Chưa xếp';
  
  const statusInfo = getStatusConfig(ticket.tinh_trang);

  let displayDate = '';
  try {
     displayDate = format(new Date(chuyenXe.ngay_khoi_hanh), 'EEEE, dd/MM/yyyy', { locale: vi });
  } catch (e) {}

  return (
    <>
      <Stack.Screen 
         options={{
           title: 'Chi tiết vé',
           headerShadowVisible: false,
           headerStyle: { backgroundColor: '#0052cc' },
           headerTintColor: '#ffffff',
           headerRight: () => (
             <TouchableOpacity onPress={shareTicket}>
               <Ionicons name="share-social-outline" size={24} color="#ffffff" />
             </TouchableOpacity>
           )
         }} 
      />
      <SafeAreaView style={styles.container}>
        <StatusBar barStyle="light-content" backgroundColor="#0052cc" />
        <View style={styles.headerBg} />
        
        <ScrollView 
          contentContainerStyle={styles.scrollContainer}
          showsVerticalScrollIndicator={false}
        >
          {/* Render Actual Ticket View */}
          <Animated.View 
            entering={FadeInDown.duration(500).springify()}
            style={styles.ticketBox}
          >
             {/* Header Status Bar */}
             <View style={[styles.ticketStatusBar, { backgroundColor: statusInfo.bg }]}>
                <Ionicons name={statusInfo.icon as any} size={18} color="#ffffff" />
                <Text style={styles.statusLabel}>{statusInfo.text}</Text>
             </View>

             <View style={styles.ticketBody}>
                <View style={styles.brandRow}>
                   <MaterialCommunityIcons name="bus-clock" size={28} color="#0052cc" />
                   <View style={{ marginLeft: 12, flex: 1 }}>
                      <Text style={styles.brandName}>{nhaXe.ten_nha_xe || "Nhà Xe"}</Text>
                      <Text style={styles.ticketCode}>Mã vé: <Text style={{ fontWeight: 'bold', color: '#0052cc' }}>{ticket.ma_ve}</Text></Text>
                   </View>
                </View>

                {/* Conditional QR: Payment visual for Pending, Boarding visual otherwise */}
                <View style={styles.qrContainer}>
                  {ticket.tinh_trang === 'dang_cho' ? (
                    <>
                       <View style={styles.payQrWrapper}>
                          <Image 
                            source={{ 
                              uri: `https://img.vietqr.io/image/${process.env.EXPO_PUBLIC_BANK_ID || 'MB'}-${process.env.EXPO_PUBLIC_ACCOUNT_NAME || '0377417720'}-compact2.png?amount=${ticket.tong_tien}&addInfo=${ticket.ma_ve}&accountName=${process.env.EXPO_PUBLIC_BANK_ACCOUNT || 'NGUYENHUUTHAI'}` 
                            }} 
                            style={styles.payQrImg}
                            resizeMode="contain"
                          />
                       </View>
                       <Text style={styles.payHintText}>Quét mã này để thanh toán vé ngay</Text>
                       <View style={styles.memoHighlight}>
                          <Text style={styles.memoLabelSmall}>Nội dung CK:</Text>
                          <Text style={styles.memoValueStrong}>{ticket.ma_ve}</Text>
                       </View>
                    </>
                  ) : (
                    <>
                       <View style={styles.qrBorder}>
                          <QRCode
                            value={ticket.ma_ve || "NO_TICKET"}
                            size={150}
                            color="#0f172a"
                            backgroundColor="#ffffff"
                          />
                       </View>
                       <Text style={styles.qrHint}>Đưa mã này cho nhân viên khi lên xe</Text>
                    </>
                  )}
                </View>

                <View style={styles.dashedSection}>
                   <View style={[styles.circleHole, { left: -31 }]} />
                   <View style={[styles.dashedLine]} />
                   <View style={[styles.circleHole, { right: -31 }]} />
                </View>

                {/* Journey Info */}
                <View style={styles.infoSection}>
                    <View style={styles.timeLocationRow}>
                        <View style={styles.dotWrapper}>
                           <View style={[styles.timelineDot, { backgroundColor: '#0052cc' }]} />
                           <View style={styles.timelineLine} />
                        </View>
                        <View style={styles.locDetails}>
                           <Text style={styles.locTitle}>Điểm khởi hành</Text>
                           <Text style={styles.locTime}>{chuyenXe.gio_khoi_hanh?.slice(0,5) || '--:--'}</Text>
                           <Text style={styles.locName}>{tuyenDuong.diem_bat_dau}</Text>
                        </View>
                    </View>
                    
                    <View style={styles.timeLocationRow}>
                        <View style={styles.dotWrapper}>
                           <View style={[styles.timelineDot, { backgroundColor: '#10b981' }]} />
                        </View>
                        <View style={styles.locDetails}>
                           <Text style={styles.locTitle}>Điểm đến</Text>
                           <Text style={styles.locTime}>--:--</Text>
                           <Text style={styles.locName}>{tuyenDuong.diem_ket_thuc}</Text>
                        </View>
                    </View>
                </View>

                <View style={styles.divider} />

                {/* Details Grid */}
                <View style={styles.gridSection}>
                   <View style={styles.gridItem}>
                      <Text style={styles.gridLabel}>Ngày đi</Text>
                      <Text style={styles.gridValue}>{displayDate || '...'}</Text>
                   </View>
                   <View style={styles.gridItem}>
                      <Text style={styles.gridLabel}>Vị trí ghế</Text>
                      <Text style={[styles.gridValue, { color: '#0052cc', fontWeight: 'bold' }]}>{listGhe}</Text>
                   </View>
                   <View style={styles.gridItem}>
                      <Text style={styles.gridLabel}>Biển số xe</Text>
                      <Text style={styles.gridValue}>{chuyenXe.xe?.bien_so || 'N/A'}</Text>
                   </View>
                   <View style={styles.gridItem}>
                      <Text style={styles.gridLabel}>Tổng thanh toán</Text>
                      <Text style={[styles.gridValue, { fontSize: 16, color: '#000000', fontWeight: '800' }]}>{formatCurrency(ticket.tong_tien)}</Text>
                   </View>
                </View>
             </View>
          </Animated.View>

          {/* Action Buttons */}
          <Animated.View entering={FadeInUp.delay(300).duration(500)} style={styles.actionGroup}>
             {ticket.tinh_trang === 'dang_cho' && (
               <TouchableOpacity 
                  style={[styles.actionBtn, styles.payBtn]}
                  onPress={() => Alert.alert("Thanh toán", "Chức năng thanh toán chuyển qua web portal.")}
               >
                  <Text style={styles.payBtnText}>Tiếp tục thanh toán</Text>
               </TouchableOpacity>
             )}

             {['dang_cho', 'da_thanh_toan'].includes(ticket.tinh_trang) && (
               <TouchableOpacity 
                  style={[styles.actionBtn, styles.cancelBtn]}
                  onPress={handleCancel}
                  disabled={cancelling}
               >
                  {cancelling ? <ActivityIndicator color="#dc2626" /> : <Text style={styles.cancelBtnText}>Yêu cầu hủy vé</Text>}
               </TouchableOpacity>
             )}
             
             <TouchableOpacity 
                style={[styles.actionBtn, styles.supportBtn]}
                onPress={() => router.push('/chat')}
             >
                <Ionicons name="chatbubble-ellipses-outline" size={20} color="#475569" />
                <Text style={styles.supportBtnText}>Hỗ trợ khách hàng</Text>
             </TouchableOpacity>
          </Animated.View>
        </ScrollView>
      </SafeAreaView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f1f5f9',
  },
  headerBg: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 200,
    backgroundColor: '#0052cc',
  },
  centeredContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff'
  },
  scrollContainer: {
    padding: 20,
    paddingBottom: 60,
  },
  ticketBox: {
    backgroundColor: '#ffffff',
    borderRadius: 20,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.1,
    shadowRadius: 20,
    elevation: 8,
  },
  ticketStatusBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    gap: 8,
  },
  statusLabel: {
    color: '#ffffff',
    fontWeight: 'bold',
    fontSize: 13,
    letterSpacing: 0.5,
  },
  ticketBody: {
    padding: 20,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 24,
  },
  brandName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1e293b',
  },
  ticketCode: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 2,
  },
  qrContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 10,
  },
  qrBorder: {
    padding: 12,
    backgroundColor: '#ffffff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
  },
  qrHint: {
    marginTop: 16,
    fontSize: 13,
    color: '#94a3b8',
  },
  // New Pay QR styles
  payQrWrapper: {
    width: 200,
    height: 200,
    padding: 8,
    backgroundColor: '#ffffff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  payQrImg: {
    width: '100%',
    height: '100%',
  },
  payHintText: {
    marginTop: 12,
    fontSize: 14,
    color: '#0052cc',
    fontWeight: '600',
  },
  memoHighlight: {
    marginTop: 12,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  memoLabelSmall: {
    fontSize: 12,
    color: '#64748b',
  },
  memoValueStrong: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    letterSpacing: 0.5,
  },
  dashedSection: {
    marginVertical: 24,
    flexDirection: 'row',
    alignItems: 'center',
    position: 'relative',
  },
  circleHole: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: '#f1f5f9',
    position: 'absolute',
    zIndex: 2,
  },
  dashedLine: {
    flex: 1,
    height: 1,
    borderWidth: 1,
    borderColor: '#cbd5e1',
    borderStyle: 'dashed',
    borderRadius: 1,
  },
  infoSection: {
    paddingHorizontal: 10,
  },
  timeLocationRow: {
    flexDirection: 'row',
    minHeight: 60,
  },
  dotWrapper: {
    alignItems: 'center',
    marginRight: 16,
    width: 16,
  },
  timelineDot: {
    width: 14,
    height: 14,
    borderRadius: 7,
    marginTop: 4,
  },
  timelineLine: {
    flex: 1,
    width: 2,
    backgroundColor: '#e2e8f0',
    marginVertical: 2,
  },
  locDetails: {
    flex: 1,
    paddingBottom: 20,
  },
  locTitle: {
    fontSize: 12,
    color: '#94a3b8',
    marginBottom: 2,
  },
  locTime: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
  },
  locName: {
    fontSize: 15,
    color: '#475569',
    marginTop: 4,
  },
  divider: {
    height: 1,
    backgroundColor: '#f1f5f9',
    marginVertical: 16,
  },
  gridSection: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  gridItem: {
    width: '50%',
    marginBottom: 16,
  },
  gridLabel: {
    fontSize: 12,
    color: '#94a3b8',
    marginBottom: 4,
  },
  gridValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#334155',
  },
  actionGroup: {
    marginTop: 24,
    gap: 12,
  },
  actionBtn: {
    height: 52,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  payBtn: {
    backgroundColor: '#0052cc',
  },
  payBtnText: {
    color: '#ffffff',
    fontWeight: 'bold',
    fontSize: 16,
  },
  cancelBtn: {
    backgroundColor: '#fef2f2',
    borderWidth: 1,
    borderColor: '#fee2e2',
  },
  cancelBtnText: {
    color: '#dc2626',
    fontWeight: '600',
    fontSize: 15,
  },
  supportBtn: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  supportBtnText: {
    color: '#475569',
    fontWeight: '600',
    fontSize: 15,
  },
  backBtn: {
    backgroundColor: '#0052cc',
    paddingVertical: 10,
    paddingHorizontal: 24,
    borderRadius: 8,
  }
});
