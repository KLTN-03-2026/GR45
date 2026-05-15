import React, { useEffect, useState, useMemo } from 'react';
import { StyleSheet, Text, View, ScrollView, ActivityIndicator, TouchableOpacity, Alert, Dimensions, TextInput, KeyboardAvoidingView, Platform, Image } from 'react-native';
import { useLocalSearchParams, useRouter, Stack } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons, MaterialCommunityIcons, FontAwesome5 } from '@expo/vector-icons';
import Animated, { FadeIn, FadeInRight, FadeInLeft } from 'react-native-reanimated';
import clientApi from '@/src/services/client-api';
import { createEcho } from '@/src/utils/echo';

const { width } = Dimensions.get('window');

type StepType = 'seat' | 'stations' | 'payment' | 'qr';

export default function BookingScreen() {
  const { tripId } = useLocalSearchParams<{ tripId: string }>();
  const router = useRouter();

  // Loading states
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [currentStep, setCurrentStep] = useState<StepType>('seat');

  // Data from APIs
  const [tripData, setTripData] = useState<any>(null);
  const [rawSeats, setRawSeats] = useState<any[]>([]);
  const [stops, setStops] = useState<any>({ tram_don: [], tram_tra: [] });
  const [vouchers, setVouchers] = useState<any[]>([]);
  const [loyaltyInfo, setLoyaltyInfo] = useState<any>(null);

  // Selected inputs
  const [selectedSeats, setSelectedSeats] = useState<any[]>([]);
  const [pickupPointId, setPickupPointId] = useState<number | null>(null);
  const [dropoffPointId, setDropoffPointId] = useState<number | null>(null);
  const [note, setNote] = useState('');
  const [selectedVoucherId, setSelectedVoucherId] = useState<number | null>(null);
  const [pointsToRedeem, setPointsToRedeem] = useState<number>(0);
  const [paymentMethod, setPaymentMethod] = useState('tien_mat'); // defaults

  // Booking result for Realtime & QR
  const [bookingResult, setBookingResult] = useState<any>(null);

  useEffect(() => {
    fetchInitialData();
  }, [tripId]);

  const fetchInitialData = async () => {
    if (!tripId) return;
    try {
      setLoading(true);
      const [seatsRes, stopsRes, loyaltyRes] = await Promise.all([
         clientApi.getTripSeats(tripId),
         clientApi.getTripStops(tripId),
         clientApi.getLoyaltyInfo().catch(() => ({ data: { data: null } })) // fallback if guest or error
      ]);

      if (seatsRes.data && seatsRes.data.success) {
         setTripData(seatsRes.data.data.chuyen_xe);
         
         const seatData = seatsRes.data.data.so_do_ghe;
         let arrSeats = Array.isArray(seatData) ? seatData : (Object.values(seatData).flat());
         setRawSeats(arrSeats);

         // Auto adjust payment method if cashless required
         if (seatsRes.data.data.chuyen_xe?.thanh_toan_sau === 0) {
            setPaymentMethod('chuyen_khoan');
         }

         // Fetch vouchers using operator id
         try {
            const maNhaXe = seatsRes.data.data.chuyen_xe?.tuyen_duong?.ma_nha_xe;
            if (maNhaXe) {
               const vRes = await clientApi.getMyVouchers({ ma_nha_xe: maNhaXe, usable_only: 1 });
               setVouchers(vRes.data?.data || []);
            }
         } catch(e) {}
      }

      if (stopsRes.data && stopsRes.data.success) {
         setStops(stopsRes.data.data);
      }

      if (loyaltyRes.data && loyaltyRes.data.success) {
         setLoyaltyInfo(loyaltyRes.data.data);
      }

    } catch(e) {
      console.error(e);
      Alert.alert("Lỗi", "Không thể lấy thông tin đặt ghế.");
    } finally {
      setLoading(false);
    }
  };

  // Computed logic using useMemo equivalent
  const seatsByFloor = useMemo(() => {
     const floors: Record<number, any[]> = {};
     rawSeats.forEach((seat: any) => {
        const f = Number(seat.tang || 1);
        if (!floors[f]) floors[f] = [];
        floors[f].push(seat);
     });

     return Object.entries(floors)
       .sort((a, b) => Number(a[0]) - Number(b[0]))
       .map(([floor, list]) => ({
          floor: Number(floor),
          seats: list.sort((x, y) => String(x.ma_ghe).localeCompare(String(y.ma_ghe)))
       }));
  }, [rawSeats]);

  const baseTotalPrice = useMemo(() => {
     if (!tripData) return 0;
     const base = parseFloat(tripData.tuyen_duong?.gia_ve_co_ban || 0);
     return base * selectedSeats.length;
  }, [tripData, selectedSeats]);

  const currentVoucher = useMemo(() => {
     return vouchers.find(v => v.id === selectedVoucherId) || null;
  }, [vouchers, selectedVoucherId]);

  const discountAmount = useMemo(() => {
     if (!currentVoucher) return 0;
     let d = 0;
     if (currentVoucher.loai_voucher === 'percent') {
        d = (baseTotalPrice * parseFloat(currentVoucher.gia_tri)) / 100;
     } else {
        d = parseFloat(currentVoucher.gia_tri);
     }
     return Math.min(d, baseTotalPrice);
  }, [currentVoucher, baseTotalPrice]);

  const pointsDiscountAmount = useMemo(() => {
     return (Number(pointsToRedeem) || 0) * 100;
  }, [pointsToRedeem]);
  
  const finalTotal = Math.max(0, baseTotalPrice - discountAmount - pointsDiscountAmount);

  const handleToggleSeat = (seat: any) => {
     if (seat.trang_thai !== 'trong') return;
     
     const exists = selectedSeats.find(s => s.id_ghe === seat.id_ghe);
     if (exists) {
        setSelectedSeats(selectedSeats.filter(s => s.id_ghe !== seat.id_ghe));
     } else {
        if (selectedSeats.length >= 6) {
           Alert.alert("Thông báo", "Chỉ cho phép chọn tối đa 6 ghế 1 lần đặt.");
           return;
        }
        setSelectedSeats([...selectedSeats, seat]);
     }
  };

  const handleBook = async () => {
     try {
       setSubmitting(true);
       const payload = {
         id_chuyen_xe: tripData.id,
         danh_sach_ghe: selectedSeats.map(s => s.ma_ghe),
         id_tram_don: pickupPointId,
         id_tram_tra: dropoffPointId,
         ghi_chu: note,
         id_voucher: selectedVoucherId,
         diem_quy_doi: Number(pointsToRedeem) || 0,
         phuong_thuc_thanh_toan: paymentMethod
       };

       const res = await clientApi.bookTicket(payload);
       if (res.data && res.data.success) {
          const bookedTicket = res.data.data;
          setBookingResult(bookedTicket);

          if (paymentMethod === 'chuyen_khoan') {
             // Switch to Realtime QR Screen
             setCurrentStep('qr');
             setupRealtimeListener(bookedTicket.ma_ve, bookedTicket.id);
          } else {
             // Redirect for cash 
             Alert.alert("Thành công", "Đặt vé thành công! Hãy thanh toán khi lên xe.", [
                { text: "Đồng ý", onPress: () => router.replace(`/ticket/${bookedTicket.id}`) }
             ]);
          }
       } else {
          Alert.alert("Lỗi", res.data?.message || "Đặt vé không thành công.");
       }
     } catch (err: any) {
         console.error("❌ LỖI TRONG QUÁ TRÌNH ĐẶT VÉ:", err);
         if (err.response) {
            console.error("Chi tiết response lỗi:", err.response.data);
         }
         Alert.alert("Lỗi", err.response?.data?.message || "Đặt vé xảy ra sự cố.");
      } finally {
        setSubmitting(false);
     }
  };

  const setupRealtimeListener = (maVe: string, ticketId: number) => {
     const echo = createEcho();
     if (!echo) return;

     console.log(`[ECHO] Subscribing to channel ve.${maVe}`);
     try {
        echo.channel(`ve.${maVe}`)
           .listen('.ve.da_thanh_toan', (e: any) => {
              console.log("[ECHO] ✅ Nhận thông báo Đã thanh toán!", e);
              setBookingResult((prev: any) => ({ ...prev, tinh_trang: 'da_thanh_toan' }));
              Alert.alert("Thanh toán thành công 🎉", "Hệ thống đã ghi nhận thanh toán của bạn.", [
                 { text: "Xem vé ngay", onPress: () => router.replace(`/ticket/${ticketId}`) }
              ]);
           })
           .listen('.ve.huy_tu_dong', (e: any) => {
              console.log("[ECHO] ❌ Hết hạn thanh toán", e);
              setBookingResult((prev: any) => ({ ...prev, tinh_trang: 'huy' }));
              Alert.alert("Hết thời gian", "Giao dịch thanh toán của bạn đã bị hủy do hết hạn.");
           });
        console.log(`[ECHO] Subscription complete for channel ve.${maVe}`);
     } catch (listenError) {
        console.error("❌ LỖI KHI GẮN LISTEN CHO KÊNH:", listenError);
     }
  };

  // Cleanup hook
  useEffect(() => {
     return () => {
        if (bookingResult?.ma_ve) {
           const echo = createEcho();
           if (echo) {
              echo.leaveChannel(`ve.${bookingResult.ma_ve}`);
           }
        }
     };
  }, [bookingResult?.ma_ve]);

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0052cc" />
        <Text style={{ marginTop: 10, color: '#64748b' }}>Đang tải sơ đồ ghế...</Text>
      </View>
    );
  }

  // STEPS RENDERER
  const renderSeatSelection = () => (
    <Animated.ScrollView entering={FadeInRight} style={styles.stepContainer} showsVerticalScrollIndicator={false}>
       <View style={styles.infoBox}>
          <Text style={styles.infoTitle}>Vui lòng chạm vào vị trí ghế muốn chọn</Text>
          <View style={styles.legendRow}>
             <View style={styles.legendItem}><View style={[styles.dot, { backgroundColor: '#ffffff', borderWidth: 1, borderColor: '#cbd5e1' }]} /><Text style={styles.legendTxt}>Trống</Text></View>
             <View style={styles.legendItem}><View style={[styles.dot, { backgroundColor: '#0052cc' }]} /><Text style={styles.legendTxt}>Đang chọn</Text></View>
             <View style={styles.legendItem}><View style={[styles.dot, { backgroundColor: '#e2e8f0' }]} /><Text style={styles.legendTxt}>Đã đặt</Text></View>
          </View>
       </View>

       {seatsByFloor.map((floorItem) => (
         <View key={floorItem.floor} style={styles.floorBlock}>
            <Text style={styles.floorTitle}>Tầng {floorItem.floor}</Text>
            <View style={styles.seatGrid}>
               {floorItem.seats.map((seat) => {
                 const isSelected = selectedSeats.some(s => s.id_ghe === seat.id_ghe);
                 const isBooked = seat.trang_thai !== 'trong';
                 return (
                   <TouchableOpacity
                     key={seat.id_ghe}
                     disabled={isBooked}
                     style={[
                       styles.seatUnit,
                       isSelected && styles.seatSelected,
                       isBooked && styles.seatBooked
                     ]}
                     onPress={() => handleToggleSeat(seat)}
                   >
                     <MaterialCommunityIcons 
                       name="seat" 
                       size={26} 
                       color={isSelected ? '#fff' : (isBooked ? '#cbd5e1' : '#64748b')} 
                     />
                     <Text style={[styles.seatLabel, isSelected && { color: '#fff' }]}>{seat.ma_ghe}</Text>
                   </TouchableOpacity>
                 );
               })}
            </View>
         </View>
       ))}
       
       <View style={{ height: 120 }} />
    </Animated.ScrollView>
  );

  const renderStations = () => (
     <Animated.ScrollView entering={FadeIn} style={styles.stepContainer} showsVerticalScrollIndicator={false}>
        <Text style={styles.stepLabel}>Chọn điểm đón</Text>
        {stops.tram_don.map((s: any) => (
          <TouchableOpacity 
             key={s.id} 
             style={[styles.stationCard, pickupPointId === s.id && styles.stationActive]} 
             onPress={() => setPickupPointId(s.id)}
          >
             <View style={[styles.radioCircle, pickupPointId === s.id && styles.radioCircleActive]} />
             <View style={{ flex: 1 }}>
                <Text style={styles.stationName}>{s.ten_tram}</Text>
                <Text style={styles.stationAddr}>{s.dia_chi}</Text>
             </View>
          </TouchableOpacity>
        ))}

        <Text style={[styles.stepLabel, { marginTop: 24 }]}>Chọn điểm trả</Text>
        {stops.tram_tra.map((s: any) => (
          <TouchableOpacity 
             key={s.id} 
             style={[styles.stationCard, dropoffPointId === s.id && styles.stationActive]} 
             onPress={() => setDropoffPointId(s.id)}
          >
             <View style={[styles.radioCircle, dropoffPointId === s.id && styles.radioCircleActive]} />
             <View style={{ flex: 1 }}>
                <Text style={styles.stationName}>{s.ten_tram}</Text>
                <Text style={styles.stationAddr}>{s.dia_chi}</Text>
             </View>
          </TouchableOpacity>
        ))}

        <Text style={[styles.stepLabel, { marginTop: 24 }]}>Ghi chú cho nhà xe (nếu có)</Text>
        <TextInput 
           style={styles.textArea} 
           multiline 
           numberOfLines={3} 
           placeholder="Nhập yêu cầu của bạn..."
           value={note}
           onChangeText={setNote}
        />
        <View style={{ height: 100 }} />
     </Animated.ScrollView>
  );

  const renderPayment = () => (
     <Animated.ScrollView entering={FadeInLeft} style={styles.stepContainer} showsVerticalScrollIndicator={false}>
        <Text style={styles.stepLabel}>Mã khuyến mãi</Text>
        {vouchers.length === 0 ? (
           <View style={styles.emptyMsg}><Text style={{ color: '#94a3b8' }}>Hiện không có voucher khả dụng.</Text></View>
        ) : (
           <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 12, paddingVertical: 8 }}>
              {vouchers.map(v => (
                 <TouchableOpacity 
                   key={v.id} 
                   style={[styles.voucherChip, selectedVoucherId === v.id && styles.voucherActive]}
                   onPress={() => setSelectedVoucherId(selectedVoucherId === v.id ? null : v.id)}
                 >
                    <Text style={[styles.vTitle, selectedVoucherId === v.id && { color: '#fff' }]}>{v.ten_voucher}</Text>
                    <Text style={[styles.vValue, selectedVoucherId === v.id && { color: '#fff' }]}>
                       Giảm {v.loai_voucher === 'percent' ? `${v.gia_tri}%` : formatCurrency(v.gia_tri)}
                    </Text>
                 </TouchableOpacity>
              ))}
           </ScrollView>
        )}

        {/* Loyalty Points Section */}
        {loyaltyInfo && loyaltyInfo.diem_hien_tai > 0 && (
           <View style={{ marginTop: 24 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                 <Text style={styles.stepLabel}>Đổi điểm thưởng</Text>
                 <Text style={{ fontSize: 13, color: '#64748b' }}>Bạn có: <Text style={{ fontWeight: 'bold', color: '#0052cc' }}>{loyaltyInfo.diem_hien_tai}</Text> điểm</Text>
              </View>
              
              <View style={styles.loyaltyBox}>
                 <TextInput
                    style={styles.loyaltyInput}
                    keyboardType="numeric"
                    placeholder="Nhập số điểm muốn dùng"
                    value={pointsToRedeem.toString()}
                    onChangeText={(val) => {
                       const num = parseInt(val.replace(/[^0-9]/g, '')) || 0;
                       
                       // Giới hạn 1: Không vượt quá điểm hiện có
                       let safeNum = Math.min(num, loyaltyInfo.diem_hien_tai);
                       
                       // Giới hạn 2: Không vượt quá số tiền cần trả
                       const remainingPrice = baseTotalPrice - discountAmount;
                       const maxPointsForPrice = Math.floor(remainingPrice / 100);
                       safeNum = Math.min(safeNum, maxPointsForPrice);
                       
                       setPointsToRedeem(safeNum);
                    }}
                 />
                 <TouchableOpacity 
                    style={styles.maxBtn}
                    onPress={() => {
                       const remainingPrice = baseTotalPrice - discountAmount;
                       const maxPointsForPrice = Math.floor(remainingPrice / 100);
                       setPointsToRedeem(Math.min(loyaltyInfo.diem_hien_tai, maxPointsForPrice));
                    }}
                 >
                    <Text style={styles.maxBtnTxt}>Dùng tối đa</Text>
                 </TouchableOpacity>
              </View>
              <Text style={styles.loyaltyNote}>1 điểm = 100đ. Giảm tối đa {formatCurrency(pointsDiscountAmount)}</Text>
           </View>
        )}

        <Text style={[styles.stepLabel, { marginTop: 24 }]}>Hình thức thanh toán</Text>
        {tripData?.thanh_toan_sau !== 0 && (
           <TouchableOpacity 
             style={[styles.payMethod, paymentMethod === 'tien_mat' && styles.payActive]}
             onPress={() => setPaymentMethod('tien_mat')}
           >
              <MaterialCommunityIcons name="cash" size={24} color="#475569" />
              <Text style={styles.payLabel}>Thanh toán khi lên xe</Text>
              {paymentMethod === 'tien_mat' && <Ionicons name="checkmark-circle" size={20} color="#0052cc" />}
           </TouchableOpacity>
        )}
        <TouchableOpacity 
           style={[styles.payMethod, paymentMethod === 'chuyen_khoan' && styles.payActive]}
           onPress={() => setPaymentMethod('chuyen_khoan')}
        >
           <MaterialCommunityIcons name="bank" size={22} color="#475569" />
           <Text style={styles.payLabel}>Chuyển khoản ngân hàng</Text>
           {paymentMethod === 'chuyen_khoan' && <Ionicons name="checkmark-circle" size={20} color="#0052cc" />}
        </TouchableOpacity>

        <View style={styles.summaryBox}>
           <Text style={styles.summaryTitle}>Chi tiết thanh toán</Text>
           <View style={styles.summaryRow}><Text style={styles.sTxt}>Giá gốc x {selectedSeats.length} vé</Text><Text style={styles.sVal}>{formatCurrency(baseTotalPrice)}</Text></View>
           {discountAmount > 0 && (
              <View style={styles.summaryRow}><Text style={styles.sTxt}>Khuyến mãi</Text><Text style={[styles.sVal, { color: '#dc2626' }]}>-{formatCurrency(discountAmount)}</Text></View>
           )}
           {pointsDiscountAmount > 0 && (
              <View style={styles.summaryRow}><Text style={styles.sTxt}>Điểm thưởng</Text><Text style={[styles.sVal, { color: '#dc2626' }]}>-{formatCurrency(pointsDiscountAmount)}</Text></View>
           )}
           <View style={styles.divider} />
           <View style={styles.summaryRow}><Text style={[styles.sTxt, { fontWeight: 'bold', color: '#0f172a' }]}>Tổng cộng</Text><Text style={styles.totalPrice}>{formatCurrency(finalTotal)}</Text></View>
        </View>
        <View style={{ height: 120 }} />
     </Animated.ScrollView>
  );

  const renderQRCodeView = () => {
     if (!bookingResult) return null;
     const bankCode = "MB";
     // Account number is flipped in env, usually is 0377417720
     const bankNo = process.env.EXPO_PUBLIC_ACCOUNT_NAME || '0377417720';
     const bankName = process.env.EXPO_PUBLIC_BANK_ACCOUNT || 'NGUYENHUUTHAI';
     
     const qrUrl = `https://img.vietqr.io/image/${bankCode}-${bankNo}-compact2.png?amount=${finalTotal}&addInfo=${bookingResult.ma_ve}&accountName=${bankName}`;
     const isPaid = bookingResult.tinh_trang === 'da_thanh_toan';
     const isCancelled = bookingResult.tinh_trang === 'huy';

     return (
        <Animated.ScrollView entering={FadeIn} style={styles.stepContainer} contentContainerStyle={{ alignItems: 'center' }} showsVerticalScrollIndicator={false}>
           <View style={styles.qrCard}>
              <View style={styles.qrHeader}>
                 <Text style={styles.qrStatus}>Đang chờ thanh toán...</Text>
                 <Text style={styles.qrPrice}>{formatCurrency(finalTotal)}</Text>
              </View>

              <View style={styles.qrWrapper}>
                 <Image 
                   source={{ uri: qrUrl }} 
                   style={[styles.qrImg, (isPaid || isCancelled) && { opacity: 0.2 }]} 
                   resizeMode="contain"
                 />
                 
                 {isPaid && (
                    <View style={styles.qrOverlay}>
                       <Ionicons name="checkmark-circle" size={64} color="#10b981" />
                       <Text style={styles.overlayText}>Đã Thanh Toán</Text>
                    </View>
                 )}
                 {isCancelled && (
                    <View style={styles.qrOverlay}>
                       <Ionicons name="close-circle" size={64} color="#ef4444" />
                       <Text style={[styles.overlayText, { color: '#ef4444' }]}>Đã Hủy</Text>
                    </View>
                 )}
              </View>

              <Text style={styles.qrInstru}>Quét mã QR bằng app Ngân hàng hoặc Ví điện tử để hoàn tất nhanh nhất.</Text>
              
              <View style={styles.memoBox}>
                 <Text style={styles.memoLabel}>Nội dung chuyển khoản:</Text>
                 <View style={styles.memoRow}>
                    <Text style={styles.memoVal}>{bookingResult.ma_ve}</Text>
                 </View>
              </View>
           </View>

           <View style={styles.waitingBox}>
              <ActivityIndicator size="small" color="#0052cc" />
              <Text style={styles.waitingTxt}>Hệ thống sẽ tự động ghi nhận khi nhận được chuyển khoản.</Text>
           </View>
           
           <TouchableOpacity 
              style={styles.cancelBtnLight}
              onPress={() => router.replace(`/ticket/${bookingResult.id}`)}
           >
              <Text style={styles.cancelBtnText}>Để sau, xem vé ngay</Text>
           </TouchableOpacity>
        </Animated.ScrollView>
     );
  };

  // Navigation Logic
  const handleNext = () => {
     if (currentStep === 'seat') {
        if (selectedSeats.length === 0) {
           Alert.alert("Chú ý", "Vui lòng chọn ít nhất 1 ghế.");
           return;
        }
        setCurrentStep('stations');
     } else if (currentStep === 'stations') {
        if (!pickupPointId || !dropoffPointId) {
           Alert.alert("Chú ý", "Vui lòng chọn đầy đủ điểm đón và trả.");
           return;
        }
        setCurrentStep('payment');
     }
  };

  const handlePrev = () => {
     if (currentStep === 'stations') setCurrentStep('seat');
     if (currentStep === 'payment') setCurrentStep('stations');
  };

  return (
     <SafeAreaView style={styles.container} edges={['bottom']}>
        <Stack.Screen options={{ 
           headerTitle: 'Đặt Vé', 
           headerShadowVisible: false,
           headerLeft: () => (
              <TouchableOpacity onPress={() => {
                 if (currentStep === 'seat' || currentStep === 'qr') router.back();
                 else handlePrev();
              }}>
                 <Ionicons name="chevron-back" size={24} color="#0f172a" />
              </TouchableOpacity>
           )
        }} />
        
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
           {/* Stepper Header - Hidden on QR view */}
           {currentStep !== 'qr' && (
              <View style={styles.stepperRow}>
                 {['seat', 'stations', 'payment'].map((st, i) => {
                    const isActive = currentStep === st;
                    const isDone = (currentStep === 'stations' && st === 'seat') || (currentStep === 'payment' && (st === 'seat' || st === 'stations'));
                    return (
                       <React.Fragment key={st}>
                          <View style={[styles.stepNode, (isActive || isDone) && styles.stepNodeActive]}>
                             {isDone ? <Ionicons name="checkmark" size={14} color="#fff" /> : <Text style={[styles.stepNodeTxt, isActive && { color: '#fff' }]}>{i + 1}</Text>}
                          </View>
                          {i < 2 && <View style={[styles.stepLine, isDone && styles.stepLineActive]} />}
                       </React.Fragment>
                    );
                 })}
              </View>
           )}

           {/* Screen Content based on Step */}
           {currentStep === 'seat' && renderSeatSelection()}
           {currentStep === 'stations' && renderStations()}
           {currentStep === 'payment' && renderPayment()}
           {currentStep === 'qr' && renderQRCodeView()}

           {/* Sticky Bottom Footer bar - Only steps 1-3 */}
           {currentStep !== 'qr' && (
              <View style={styles.footerBar}>
                 <View style={{ flex: 1 }}>
                    <Text style={styles.footerLabel}>{selectedSeats.length} ghế đã chọn</Text>
                    <Text style={styles.footerValue}>{formatCurrency(finalTotal)}</Text>
                 </View>
                 {currentStep !== 'payment' ? (
                    <TouchableOpacity style={styles.primaryBtn} onPress={handleNext}>
                       <Text style={styles.primaryBtnTxt}>Tiếp theo</Text>
                       <Ionicons name="arrow-forward" size={18} color="#fff" />
                    </TouchableOpacity>
                 ) : (
                    <TouchableOpacity 
                       style={[styles.primaryBtn, { backgroundColor: '#10b981' }]} 
                       onPress={handleBook}
                       disabled={submitting}
                    >
                       {submitting ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnTxt}>Xác nhận & Đặt</Text>}
                    </TouchableOpacity>
                 )}
              </View>
           )}
        </KeyboardAvoidingView>
     </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  stepperRow: {
     flexDirection: 'row',
     alignItems: 'center',
     justifyContent: 'center',
     paddingVertical: 16,
     backgroundColor: '#ffffff',
     borderBottomWidth: 1,
     borderBottomColor: '#f1f5f9'
  },
  stepNode: { width: 28, height: 28, borderRadius: 14, backgroundColor: '#f1f5f9', justifyContent: 'center', alignItems: 'center', borderWidth: 1, borderColor: '#e2e8f0' },
  stepNodeActive: { backgroundColor: '#0052cc', borderColor: '#0052cc' },
  stepNodeTxt: { fontSize: 12, fontWeight: 'bold', color: '#64748b' },
  stepLine: { width: 60, height: 2, backgroundColor: '#e2e8f0', marginHorizontal: 8 },
  stepLineActive: { backgroundColor: '#0052cc' },
  
  stepContainer: { flex: 1, padding: 20 },
  
  // Seat Selection Styles
  infoBox: { marginBottom: 20 },
  infoTitle: { fontSize: 14, fontWeight: '500', color: '#1e293b', marginBottom: 12 },
  legendRow: { flexDirection: 'row', gap: 16 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  dot: { width: 12, height: 12, borderRadius: 4 },
  legendTxt: { fontSize: 12, color: '#64748b' },
  
  floorBlock: { marginBottom: 24, backgroundColor: '#fff', borderRadius: 16, padding: 16, shadowColor: '#000', shadowOpacity: 0.03, shadowRadius: 8, elevation: 2 },
  floorTitle: { fontSize: 16, fontWeight: '700', color: '#0f172a', marginBottom: 16, textAlign: 'center' },
  seatGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 16 },
  seatUnit: { width: (width - 100) / 3, height: 60, backgroundColor: '#f8fafc', borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0', alignItems: 'center', justifyContent: 'center', gap: 2 },
  seatSelected: { backgroundColor: '#0052cc', borderColor: '#0052cc' },
  seatBooked: { backgroundColor: '#f1f5f9', borderColor: '#f1f5f9' },
  seatLabel: { fontSize: 12, fontWeight: '600', color: '#334155' },

  // Station/Layout Styles
  stepLabel: { fontSize: 15, fontWeight: 'bold', color: '#0f172a', marginBottom: 12 },
  stationCard: { flexDirection: 'row', backgroundColor: '#fff', padding: 16, borderRadius: 12, marginBottom: 12, borderWidth: 1, borderColor: '#e2e8f0', alignItems: 'center', gap: 12 },
  stationActive: { borderColor: '#0052cc', backgroundColor: '#eff6ff' },
  radioCircle: { width: 18, height: 18, borderRadius: 9, borderWidth: 2, borderColor: '#cbd5e1' },
  radioCircleActive: { borderColor: '#0052cc', backgroundColor: '#0052cc', borderWidth: 5 },
  stationName: { fontSize: 15, fontWeight: '600', color: '#1e293b', marginBottom: 2 },
  stationAddr: { fontSize: 13, color: '#64748b' },
  textArea: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 12, height: 80, textAlignVertical: 'top', fontSize: 14 },

  // Payment Styles
  emptyMsg: { padding: 16, backgroundColor: '#fff', borderRadius: 12, alignItems: 'center', borderStyle: 'dashed', borderWidth: 1, borderColor: '#e2e8f0' },
  voucherChip: { minWidth: 160, padding: 12, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0' },
  voucherActive: { backgroundColor: '#0052cc', borderColor: '#0052cc' },
  vTitle: { fontSize: 14, fontWeight: 'bold', color: '#0f172a', marginBottom: 4 },
  vValue: { fontSize: 12, color: '#0052cc' },
  payMethod: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 16, borderRadius: 12, gap: 12, marginBottom: 12, borderWidth: 1, borderColor: '#e2e8f0' },
  payActive: { borderColor: '#0052cc', backgroundColor: '#eff6ff' },
  payLabel: { flex: 1, fontSize: 15, color: '#1e293b', fontWeight: '500' },
  
  summaryBox: { marginTop: 24, backgroundColor: '#fff', borderRadius: 16, padding: 16, borderTopWidth: 3, borderTopColor: '#0052cc' },
  summaryTitle: { fontSize: 16, fontWeight: 'bold', color: '#0f172a', marginBottom: 16 },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
  sTxt: { color: '#64748b', fontSize: 14 },
  sVal: { fontWeight: '600', color: '#334155' },
  divider: { height: 1, backgroundColor: '#f1f5f9', marginVertical: 8 },
  totalPrice: { fontSize: 18, fontWeight: '800', color: '#0052cc' },

  // Loyalty
  loyaltyBox: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    overflow: 'hidden',
    alignItems: 'center'
  },
  loyaltyInput: {
    flex: 1,
    padding: 12,
    fontSize: 15,
    color: '#1e293b'
  },
  maxBtn: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#eff6ff',
    borderLeftWidth: 1,
    borderLeftColor: '#e2e8f0'
  },
  maxBtnTxt: {
    color: '#0052cc',
    fontSize: 13,
    fontWeight: '700'
  },
  loyaltyNote: {
    marginTop: 6,
    fontSize: 12,
    color: '#64748b',
    fontStyle: 'italic'
  },

  // QR Screen Styles
  qrCard: { width: '100%', backgroundColor: '#fff', borderRadius: 20, padding: 24, alignItems: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 12, elevation: 5, marginTop: 10 },
  qrHeader: { alignItems: 'center', marginBottom: 20 },
  qrStatus: { fontSize: 14, color: '#64748b', marginBottom: 4 },
  qrPrice: { fontSize: 28, fontWeight: '900', color: '#0f172a' },
  qrWrapper: { width: 220, height: 220, padding: 12, backgroundColor: '#fff', borderRadius: 16, borderWidth: 1, borderColor: '#e2e8f0', justifyContent: 'center', alignItems: 'center', position: 'relative', marginBottom: 20 },
  qrImg: { width: '100%', height: '100%' },
  qrOverlay: { ...StyleSheet.absoluteFillObject, justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.7)', borderRadius: 16 },
  overlayText: { marginTop: 8, fontSize: 16, fontWeight: 'bold', color: '#059669' },
  qrInstru: { fontSize: 13, color: '#64748b', textAlign: 'center', lineHeight: 18, paddingHorizontal: 12, marginBottom: 24 },
  memoBox: { width: '100%', padding: 16, backgroundColor: '#f8fafc', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0' },
  memoLabel: { fontSize: 12, color: '#64748b', marginBottom: 4, textAlign: 'center' },
  memoRow: { flexDirection: 'row', justifyContent: 'center', alignItems: 'center' },
  memoVal: { fontSize: 20, fontWeight: 'bold', color: '#0052cc', letterSpacing: 1 },
  waitingBox: { flexDirection: 'row', alignItems: 'center', marginTop: 24, gap: 10, paddingHorizontal: 32 },
  waitingTxt: { flex: 1, fontSize: 13, color: '#64748b', lineHeight: 18 },
  cancelBtnLight: { marginTop: 32, paddingVertical: 12 },
  cancelBtnText: { color: '#0052cc', fontWeight: '600', fontSize: 15, textDecorationLine: 'underline' },

  // Footer
  footerBar: {
     position: 'absolute', bottom: 0, left: 0, right: 0,
     flexDirection: 'row', alignItems: 'center', padding: 16,
     backgroundColor: '#ffffff', borderTopWidth: 1, borderTopColor: '#f1f5f9',
     paddingBottom: Platform.OS === 'ios' ? 24 : 16
  },
  footerLabel: { fontSize: 13, color: '#64748b', marginBottom: 2 },
  footerValue: { fontSize: 20, fontWeight: '800', color: '#0f172a' },
  primaryBtn: {
     flexDirection: 'row', alignItems: 'center', backgroundColor: '#0052cc',
     paddingVertical: 14, paddingHorizontal: 24, borderRadius: 12, gap: 8
  },
  primaryBtnTxt: { color: '#fff', fontWeight: 'bold', fontSize: 16 }
});
