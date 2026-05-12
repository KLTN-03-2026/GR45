import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View, FlatList, ActivityIndicator, Pressable, Alert } from 'react-native';
import { useLocalSearchParams, useRouter, Stack } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons, MaterialCommunityIcons, FontAwesome5 } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { vi } from 'date-fns/locale';
import clientApi from '@/src/services/client-api';
import Animated, { FadeInDown, LinearTransition } from 'react-native-reanimated';

export default function SearchResultsScreen() {
  const { from, to, date } = useLocalSearchParams<{ from: string, to: string, date: string }>();
  const router = useRouter();
  
  const [trips, setTrips] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
     searchTrips();
  }, [from, to, date]);

  const searchTrips = async () => {
    try {
      setLoading(true);
      const response = await clientApi.searchTrips({
         diem_di: from,
         diem_den: to,
         ngay_khoi_hanh: date
      });
      
      if (response.data && response.data.success) {
         const dataObj = response.data.data;
         // Handle standard paginated data shape
         const results = Array.isArray(dataObj) ? dataObj : (dataObj.data || []);
         setTrips(results);
      }
    } catch (e) {
      console.error("Lỗi tìm chuyến:", e);
      Alert.alert("Lỗi", "Đã xảy ra lỗi trong quá trình tìm chuyến. Thử lại sau.");
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
  };

  const renderTripItem = ({ item, index }: { item: any, index: number }) => {
     const tuyenDuong = item.tuyen_duong || item.tuyenDuong || {};
     const nhaXe = tuyenDuong.nha_xe || tuyenDuong.nhaXe || {};
     const xe = item.xe || {};
     
     // Convert timestamp if necessary for visual safety
     let timeFormatted = '--:--';
     try {
        timeFormatted = item.gio_khoi_hanh ? item.gio_khoi_hanh.slice(0,5) : '--:--';
     } catch(e) {}

     return (
       <Animated.View 
          entering={FadeInDown.delay(index * 50).springify()}
          layout={LinearTransition}
       >
          <Pressable 
            style={styles.tripCard}
            onPress={() => router.push(`/booking/${item.id}`)}
          >
             <View style={styles.tripHeader}>
                <View style={styles.nhaXeInfo}>
                   <MaterialCommunityIcons name="bus-clock" size={22} color="#0052cc" />
                   <Text style={styles.nhaXeName}>{nhaXe.ten_nha_xe || 'Nhà xe'}</Text>
                </View>
                <Text style={styles.priceText}>{formatCurrency(tuyenDuong.gia_ve_co_ban || 0)}</Text>
             </View>

             <View style={styles.tripBody}>
                <View style={styles.timelineContainer}>
                   <View style={styles.timeBox}>
                      <Text style={styles.mainTime}>{timeFormatted}</Text>
                      <Text style={styles.durationText}>Dự kiến 4-5h</Text>
                   </View>
                   
                   <View style={styles.pathVis}>
                      <View style={styles.circle} />
                      <View style={styles.dottedLine} />
                      <View style={[styles.circle, { backgroundColor: '#ef4444' }]} />
                   </View>

                   <View style={styles.locBox}>
                      <Text style={styles.locMain}>{tuyenDuong.diem_bat_dau}</Text>
                      <View style={{ flex: 1 }} />
                      <Text style={styles.locMain}>{tuyenDuong.diem_ket_thuc}</Text>
                   </View>
                </View>
             </View>

             <View style={styles.divider} />

             <View style={styles.tripFooter}>
                <View style={styles.featureItem}>
                   <Ionicons name="car-sport-outline" size={16} color="#64748b" />
                   <Text style={styles.featureText}>{xe.loai_xe?.ten_loai_xe || 'Xe CLC'}</Text>
                </View>
                <View style={styles.featureItem}>
                   <FontAwesome5 name="chair" size={14} color="#64748b" />
                   <Text style={styles.featureText}>Còn 12 chỗ</Text>
                </View>
             </View>
          </Pressable>
       </Animated.View>
     );
  };

  let friendlyDate = '...';
  try {
     friendlyDate = format(parseISO(date!), 'dd/MM/yyyy', { locale: vi });
  } catch(e){}

  return (
    <>
       <Stack.Screen 
          options={{
            headerTitle: `${from} → ${to}`,
            headerBackTitle: 'Quay lại',
            headerTitleStyle: { fontSize: 16 },
          }} 
       />
       <SafeAreaView style={styles.container} edges={['bottom']}>
          <View style={styles.subHeader}>
             <Ionicons name="calendar-outline" size={18} color="#64748b" />
             <Text style={styles.dateDisplay}>Ngày khởi hành: {friendlyDate}</Text>
          </View>

          {loading ? (
            <View style={styles.loadingArea}>
               <ActivityIndicator size="large" color="#0052cc" />
               <Text style={styles.loadingNote}>Đang quét tìm chuyến xe tối ưu...</Text>
            </View>
          ) : (
            <FlatList 
               data={trips}
               keyExtractor={(item) => item.id.toString()}
               renderItem={renderTripItem}
               contentContainerStyle={styles.listWrapper}
               ListEmptyComponent={() => (
                  <View style={styles.emptyContainer}>
                     <View style={styles.emptyIllu}>
                        <MaterialCommunityIcons name="bus-alert" size={64} color="#cbd5e1" />
                     </View>
                     <Text style={styles.emptyTitle}>Rất tiếc, không tìm thấy chuyến</Text>
                     <Text style={styles.emptyDesc}>Không có lịch trình phù hợp trong ngày này. Hãy thử đổi ngày hoặc tìm tuyến khác.</Text>
                     <Pressable 
                        style={styles.retryBtn}
                        onPress={() => router.back()}
                     >
                        <Text style={styles.retryText}>Thay đổi tìm kiếm</Text>
                     </Pressable>
                  </View>
               )}
            />
          )}
       </SafeAreaView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  subHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    gap: 8,
  },
  dateDisplay: {
    color: '#475569',
    fontWeight: '600',
  },
  loadingArea: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingNote: {
    marginTop: 16,
    fontSize: 15,
    color: '#64748b',
  },
  listWrapper: {
    padding: 16,
    paddingBottom: 40,
  },
  tripCard: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 3,
  },
  tripHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  nhaXeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  nhaXeName: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1e293b',
  },
  priceText: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0052cc',
  },
  tripBody: {
    marginBottom: 16,
  },
  timelineContainer: {
    flexDirection: 'row',
    height: 80,
  },
  timeBox: {
    width: 80,
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingRight: 12,
  },
  mainTime: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
  },
  durationText: {
    fontSize: 12,
    color: '#94a3b8',
    position: 'absolute',
    top: 32,
    right: 12,
  },
  pathVis: {
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  circle: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#0052cc',
    borderWidth: 2,
    borderColor: '#ffffff',
    elevation: 2,
  },
  dottedLine: {
    flex: 1,
    width: 2,
    backgroundColor: '#cbd5e1',
    marginVertical: 2,
  },
  locBox: {
    flex: 1,
    justifyContent: 'space-between',
    paddingLeft: 12,
    paddingVertical: 0,
  },
  locMain: {
    fontSize: 15,
    fontWeight: '600',
    color: '#334155',
  },
  divider: {
    height: 1,
    backgroundColor: '#f1f5f9',
    marginBottom: 12,
  },
  tripFooter: {
    flexDirection: 'row',
    gap: 20,
  },
  featureItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  featureText: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '500',
  },
  emptyContainer: {
    alignItems: 'center',
    marginTop: 80,
    paddingHorizontal: 32,
  },
  emptyIllu: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#f1f5f9',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#1e293b',
    textAlign: 'center',
    marginBottom: 12,
  },
  emptyDesc: {
    fontSize: 15,
    color: '#64748b',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 24,
  },
  retryBtn: {
    paddingVertical: 14,
    paddingHorizontal: 32,
    backgroundColor: '#eff6ff',
    borderRadius: 30,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  retryText: {
    color: '#1d4ed8',
    fontWeight: 'bold',
  }
});
