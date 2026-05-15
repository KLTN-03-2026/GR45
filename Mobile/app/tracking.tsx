import React, { useState, useEffect, useRef, useContext } from 'react';
import {
  StyleSheet, Text, View, TextInput, TouchableOpacity,
  ActivityIndicator, ScrollView, KeyboardAvoidingView,
  Platform, Dimensions, Image, Linking,
} from 'react-native';
import { Stack, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { AuthContext } from '@/src/store/AuthContext';
import clientApi from '@/src/services/client-api';
import Animated, { FadeIn, FadeInUp } from 'react-native-reanimated';

const { width } = Dimensions.get('window');

type UIState = 'idle' | 'loading' | 'results' | 'tracking' | 'not-found' | 'error';

export default function TrackingScreen() {
  const { user } = useContext(AuthContext);
  const router = useRouter();
  const [phone, setPhone] = useState('');
  const [trips, setTrips] = useState<any[]>([]);
  const [selectedTrip, setSelectedTrip] = useState<any>(null);
  const [liveData, setLiveData] = useState<any>(null);
  const [uiState, setUiState] = useState<UIState>('idle');
  const [errorMsg, setErrorMsg] = useState('');
  const [lastRefresh, setLastRefresh] = useState<Date | null>(null);
  const pollingRef = useRef<NodeJS.Timeout | null>(null);

  // Auto-fill phone for logged-in users
  useEffect(() => {
    if (user?.so_dien_thoai) {
      setPhone(user.so_dien_thoai);
    }
  }, [user]);

  useEffect(() => {
    return () => stopPolling();
  }, []);

  // --- LOOKUP ---
  const lookupTrips = async () => {
    const trimmed = phone.trim();
    if (!trimmed || trimmed.length < 8) {
      setErrorMsg('Vui lòng nhập số điện thoại hợp lệ.');
      setUiState('error');
      return;
    }
    setUiState('loading');
    setErrorMsg('');
    try {
      const res = await clientApi.lookupTripsByPhone({ so_dien_thoai: trimmed });
      const data = res.data?.data || [];
      const arr = Array.isArray(data) ? data : [];
      setTrips(arr);
      setUiState(arr.length === 0 ? 'not-found' : 'results');
    } catch (e: any) {
      setErrorMsg(e.response?.data?.message || 'Có lỗi xảy ra khi tra cứu.');
      setUiState('error');
    }
  };

  const selectTrip = async (trip: any) => {
    setSelectedTrip(trip);
    setUiState('tracking');
    setLiveData(null);
    await fetchLive(trip);
    startPolling(trip);
  };

  const goBack = () => {
    stopPolling();
    setSelectedTrip(null);
    setLiveData(null);
    setUiState('results');
  };

  const goHome = () => {
    stopPolling();
    setSelectedTrip(null);
    setLiveData(null);
    setTrips([]);
    setUiState('idle');
  };

  // --- LIVE DATA ---
  const fetchLive = async (trip?: any) => {
    const t = trip || selectedTrip;
    if (!t) return;
    try {
      const params = { so_dien_thoai: phone.trim() };
      const res = await clientApi.getLiveTrackingPublic(t.id, params);
      const data = res.data?.data || null;
      setLiveData(data);
      setLastRefresh(new Date());
    } catch (e) {
      console.error('Lỗi live tracking:', e);
    }
  };

  const startPolling = (trip?: any) => {
    stopPolling();
    pollingRef.current = setInterval(() => fetchLive(trip), 15000);
  };

  const stopPolling = () => {
    if (pollingRef.current) {
      clearInterval(pollingRef.current);
      pollingRef.current = null;
    }
  };

  const openInMaps = () => {
    const lat = liveData?.hien_tai?.vi_do;
    const lng = liveData?.hien_tai?.kinh_do;
    if (!lat || !lng) return;
    const url = Platform.OS === 'ios'
      ? `maps://?q=Xe+buýt&ll=${lat},${lng}`
      : `geo:${lat},${lng}?q=${lat},${lng}(Xe buýt)`;
    Linking.openURL(url).catch(() =>
      Linking.openURL(`https://www.google.com/maps?q=${lat},${lng}`)
    );
  };

  const formatLastUpdate = (seconds: number) => {
    if (!seconds && seconds !== 0) return '—';
    if (seconds < 60) return `${Math.round(seconds)}s trước`;
    if (seconds < 3600) return `${Math.round(seconds / 60)} phút trước`;
    return `${Math.round(seconds / 3600)}h trước`;
  };

  const busLat = liveData?.hien_tai?.vi_do;
  const busLng = liveData?.hien_tai?.kinh_do;
  const isLive = liveData?.meta?.is_live ?? false;

  // Static map image URL (no API key needed)
  const staticMapUrl = busLat && busLng
    ? `https://staticmap.openstreetmap.de/staticmap.php?center=${busLat},${busLng}&zoom=14&size=${Math.round(width - 32)}x240&markers=${busLat},${busLng},red-bus`
    : null;

  // =========== RENDER ===========
  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <Stack.Screen options={{ headerShown: false }} />

      {/* Hero Header */}
      <LinearGradient colors={['#1e3a5f', '#1e40af', '#3b82f6']} style={styles.hero}>
        {/* Back arrow for tracking state */}
        {uiState === 'tracking' ? (
          <View style={styles.trackingNavRow}>
            <TouchableOpacity onPress={goBack} style={styles.navBtn}>
              <Ionicons name="arrow-back" size={18} color="#475569" />
              <Text style={styles.navBtnTxt}>Quay lại</Text>
            </TouchableOpacity>
            <TouchableOpacity onPress={goHome} style={styles.navBtn}>
              <Ionicons name="close" size={20} color="#475569" />
            </TouchableOpacity>
          </View>
        ) : (
          <View style={styles.heroInner}>
            <Ionicons name="locate" size={36} color="rgba(255,255,255,0.85)" />
            <Text style={styles.heroTitle}>Theo dõi chuyến xe</Text>
            <Text style={styles.heroDesc}>Nhập SĐT để xem vị trí xe thời gian thực</Text>
          </View>
        )}

        {/* Search bar — hidden during tracking */}
        {uiState !== 'tracking' && (
          <View style={styles.searchCard}>
            <View style={styles.searchRow}>
              <View style={styles.searchInputWrap}>
                <Ionicons name="phone-portrait-outline" size={20} color="#64748b" style={{ marginRight: 8 }} />
                <TextInput
                  style={styles.searchInput}
                  placeholder="Số điện thoại đặt vé..."
                  placeholderTextColor="#94a3b8"
                  keyboardType="phone-pad"
                  value={phone}
                  onChangeText={setPhone}
                  onSubmitEditing={lookupTrips}
                  returnKeyType="search"
                />
                {phone.length > 0 && (
                  <TouchableOpacity onPress={() => { setPhone(''); setUiState('idle'); }}>
                    <Ionicons name="close-circle" size={18} color="#94a3b8" />
                  </TouchableOpacity>
                )}
              </View>
              <TouchableOpacity
                style={[styles.searchBtn, uiState === 'loading' && { opacity: 0.7 }]}
                onPress={lookupTrips}
                disabled={uiState === 'loading'}
              >
                {uiState === 'loading'
                  ? <ActivityIndicator size="small" color="#fff" />
                  : <Ionicons name="search" size={20} color="#fff" />}
              </TouchableOpacity>
            </View>

            {uiState === 'error' && (
              <View style={styles.alertBox}>
                <Ionicons name="alert-circle-outline" size={16} color="#dc2626" />
                <Text style={styles.alertTxt}>{errorMsg}</Text>
              </View>
            )}
          </View>
        )}
      </LinearGradient>

      {/* ===== CONTENT AREA ===== */}
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView style={{ flex: 1 }} contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>

          {/* IDLE */}
          {uiState === 'idle' && (
            <Animated.View entering={FadeIn} style={styles.stepsRow}>
              {[
                { icon: 'phone-portrait-outline', title: 'Nhập SĐT', desc: 'SĐT đã dùng khi đặt vé' },
                { icon: 'search-outline', title: 'Tra cứu', desc: 'Tìm chuyến đang di chuyển' },
                { icon: 'map-outline', title: 'Theo dõi', desc: 'Xem vị trí xe trực tiếp' },
              ].map((step, i) => (
                <View key={i} style={styles.stepCard}>
                  <View style={styles.stepIconWrap}>
                    <Ionicons name={step.icon as any} size={26} color="#3b82f6" />
                  </View>
                  <Text style={styles.stepTitle}>{step.title}</Text>
                  <Text style={styles.stepDesc}>{step.desc}</Text>
                </View>
              ))}
            </Animated.View>
          )}

          {/* NOT FOUND */}
          {uiState === 'not-found' && (
            <Animated.View entering={FadeIn} style={styles.centerBox}>
              <Ionicons name="bus-outline" size={64} color="#cbd5e1" />
              <Text style={styles.notFoundTitle}>Không tìm thấy chuyến xe</Text>
              <Text style={styles.notFoundDesc}>
                Không có chuyến đang di chuyển với số{'\n'}
                <Text style={{ fontWeight: '700', color: '#334155' }}>{phone}</Text>.
              </Text>
            </Animated.View>
          )}

          {/* RESULTS */}
          {uiState === 'results' && (
            <Animated.View entering={FadeIn}>
              <View style={styles.resultsHeader}>
                <Ionicons name="bus" size={16} color="#3b82f6" />
                <Text style={styles.resultsTitle}>Tìm thấy {trips.length} chuyến đang di chuyển</Text>
              </View>
              {trips.map((trip) => (
                <TouchableOpacity key={trip.id} style={styles.tripCard} onPress={() => selectTrip(trip)} activeOpacity={0.8}>
                  <View style={styles.tripCardHeader}>
                    <Text style={styles.tripRoute}>
                      {trip.tuyen_duong?.diem_bat_dau || '?'} → {trip.tuyen_duong?.diem_ket_thuc || '?'}
                    </Text>
                    <View style={[styles.liveBadge, trip.is_live && styles.liveBadgeOn]}>
                      <View style={[styles.liveDot, trip.is_live && styles.liveDotOn]} />
                      <Text style={[styles.liveBadgeTxt, trip.is_live && { color: '#16a34a' }]}>
                        {trip.is_live ? 'LIVE' : 'Chờ tín hiệu'}
                      </Text>
                    </View>
                  </View>
                  <View style={styles.tripMeta}>
                    {[
                      { icon: 'navigate-outline', val: trip.tuyen_duong?.ten_tuyen_duong },
                      { icon: 'business-outline', val: trip.nha_xe?.ten_nha_xe },
                      { icon: 'bus-outline', val: trip.xe?.bien_so ? `${trip.xe.ten_xe ? trip.xe.ten_xe + ' — ' : ''}${trip.xe.bien_so}` : null },
                      { icon: 'time-outline', val: `Khởi hành: ${trip.gio_khoi_hanh}` },
                    ].filter(m => m.val).map((m, i) => (
                      <View key={i} style={styles.tripMetaRow}>
                        <Ionicons name={m.icon as any} size={13} color="#94a3b8" />
                        <Text style={styles.tripMetaTxt}>{m.val}</Text>
                      </View>
                    ))}
                  </View>
                  <View style={styles.tripAction}>
                    <Text style={styles.tripActionTxt}>Xem trực tiếp</Text>
                    <Ionicons name="arrow-forward" size={16} color="#3b82f6" />
                  </View>
                </TouchableOpacity>
              ))}
            </Animated.View>
          )}

          {/* TRACKING */}
          {uiState === 'tracking' && (
            <Animated.View entering={FadeInUp.springify()}>
              {/* Trip title */}
              <View style={styles.trackingTitleRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.trackingRoute}>
                    {selectedTrip?.tuyen_duong?.diem_bat_dau} → {selectedTrip?.tuyen_duong?.diem_ket_thuc}
                  </Text>
                  <Text style={styles.trackingSubRoute}>{selectedTrip?.tuyen_duong?.ten_tuyen_duong}</Text>
                </View>
                <View style={[styles.liveBadge, isLive && styles.liveBadgeOn]}>
                  <View style={[styles.liveDot, isLive && styles.liveDotOn]} />
                  <Text style={[styles.liveBadgeTxt, isLive && { color: '#16a34a' }]}>
                    {isLive ? 'LIVE' : 'Chờ tín hiệu'}
                  </Text>
                </View>
              </View>

              {/* Map area */}
              <View style={styles.mapCard}>
                {!liveData ? (
                  <View style={styles.mapPlaceholder}>
                    <ActivityIndicator size="large" color="#3b82f6" />
                    <Text style={styles.mapPlaceholderTxt}>Đang tải vị trí xe...</Text>
                  </View>
                ) : !busLat ? (
                  <View style={styles.mapPlaceholder}>
                    <Ionicons name="location-outline" size={48} color="#94a3b8" />
                    <Text style={styles.mapPlaceholderTxt}>Chưa có tín hiệu GPS</Text>
                    <Text style={{ fontSize: 12, color: '#94a3b8', marginTop: 4 }}>Bản đồ sẽ cập nhật tự động sau 15 giây</Text>
                  </View>
                ) : (
                  <>
                    <Image
                      source={{ uri: `https://staticmap.openstreetmap.de/staticmap.php?center=${busLat},${busLng}&zoom=14&size=${Math.round(width - 32)}x240&markers=${busLat},${busLng},red-bus` }}
                      style={styles.mapImage}
                      resizeMode="cover"
                    />
                    <TouchableOpacity style={styles.openMapBtn} onPress={openInMaps}>
                      <Ionicons name="open-outline" size={14} color="#3b82f6" />
                      <Text style={styles.openMapBtnTxt}>Mở bản đồ</Text>
                    </TouchableOpacity>
                  </>
                )}
              </View>

              {/* Stats grid */}
              <View style={styles.statsGrid}>
                {[
                  { icon: 'business-outline', label: 'Nhà xe', value: selectedTrip?.nha_xe?.ten_nha_xe || '—' },
                  { icon: 'bus-outline', label: 'Biển số', value: selectedTrip?.xe?.bien_so || '—' },
                  { icon: 'speedometer-outline', label: 'Vận tốc', value: liveData?.hien_tai?.van_toc != null ? `${Math.round(liveData.hien_tai.van_toc)} km/h` : '—' },
                  { icon: 'time-outline', label: 'Cập nhật', value: formatLastUpdate(liveData?.meta?.last_update_seconds) },
                ].map((stat, i) => (
                  <View key={i} style={styles.statCard}>
                    <Ionicons name={stat.icon as any} size={18} color="#3b82f6" />
                    <Text style={styles.statLabel}>{stat.label}</Text>
                    <Text style={styles.statValue} numberOfLines={1}>{stat.value}</Text>
                  </View>
                ))}
              </View>

              {/* Last update */}
              {lastRefresh && (
                <Text style={styles.lastRefreshTxt}>
                  Cập nhật lúc {lastRefresh.toLocaleTimeString('vi-VN')} • Tự động làm mới sau 15 giây
                </Text>
              )}

              {/* Manual refresh */}
              <TouchableOpacity style={styles.refreshBtn} onPress={() => fetchLive()}>
                <Ionicons name="refresh-outline" size={18} color="#3b82f6" />
                <Text style={styles.refreshBtnTxt}>Làm mới ngay</Text>
              </TouchableOpacity>
            </Animated.View>
          )}

        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },

  // Hero
  hero: { paddingBottom: 8 },
  heroInner: { paddingHorizontal: 20, paddingTop: 16, paddingBottom: 10, alignItems: 'center' },
  heroTitle: { fontSize: 22, fontWeight: '800', color: '#fff', marginTop: 8, marginBottom: 4 },
  heroDesc: { fontSize: 13, color: 'rgba(255,255,255,0.8)', textAlign: 'center' },

  trackingNavRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingHorizontal: 16, paddingVertical: 12,
    backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#f1f5f9',
  },
  navBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, padding: 4 },
  navBtnTxt: { fontSize: 14, fontWeight: '600', color: '#475569' },

  // Search card
  searchCard: {
    margin: 16, backgroundColor: '#fff', borderRadius: 16, padding: 12,
    shadowColor: '#000', shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12, shadowRadius: 12, elevation: 6,
  },
  searchRow: { flexDirection: 'row', gap: 10, alignItems: 'center' },
  searchInputWrap: {
    flex: 1, flexDirection: 'row', alignItems: 'center',
    backgroundColor: '#f1f5f9', borderRadius: 12, paddingHorizontal: 12, paddingVertical: 10,
  },
  searchInput: { flex: 1, fontSize: 15, fontWeight: '600', color: '#1e293b' },
  searchBtn: {
    width: 46, height: 46, borderRadius: 12,
    backgroundColor: '#3b82f6', justifyContent: 'center', alignItems: 'center',
  },
  alertBox: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    marginTop: 10, padding: 10, backgroundColor: '#fef2f2', borderRadius: 10,
  },
  alertTxt: { color: '#dc2626', fontSize: 13, fontWeight: '500', flex: 1 },

  // Steps (idle)
  stepsRow: { flexDirection: 'row', gap: 10, padding: 16 },
  stepCard: {
    flex: 1, backgroundColor: '#fff', borderRadius: 14, padding: 12,
    alignItems: 'center', borderWidth: 1, borderColor: '#e2e8f0',
  },
  stepIconWrap: {
    width: 48, height: 48, borderRadius: 24, backgroundColor: '#eff6ff',
    justifyContent: 'center', alignItems: 'center', marginBottom: 8,
  },
  stepTitle: { fontSize: 12, fontWeight: '700', color: '#1e293b', marginBottom: 4, textAlign: 'center' },
  stepDesc: { fontSize: 10, color: '#64748b', textAlign: 'center', lineHeight: 14 },

  // Not found
  centerBox: { alignItems: 'center', paddingTop: 60, padding: 40 },
  notFoundTitle: { fontSize: 17, fontWeight: '700', color: '#334155', marginTop: 16, marginBottom: 8 },
  notFoundDesc: { fontSize: 14, color: '#64748b', textAlign: 'center', lineHeight: 22 },

  // Results
  resultsHeader: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    paddingHorizontal: 16, paddingTop: 16, marginBottom: 8,
  },
  resultsTitle: { fontSize: 14, fontWeight: '700', color: '#1e293b' },
  tripCard: {
    backgroundColor: '#fff', borderRadius: 16, marginHorizontal: 16, marginBottom: 12,
    borderWidth: 1.5, borderColor: '#e2e8f0', overflow: 'hidden',
    shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 8, elevation: 2,
  },
  tripCardHeader: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    padding: 14, paddingBottom: 8,
  },
  tripRoute: { fontSize: 15, fontWeight: '800', color: '#1e40af', flex: 1 },
  liveBadge: {
    flexDirection: 'row', alignItems: 'center', gap: 4,
    paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20, backgroundColor: '#f1f5f9',
  },
  liveBadgeOn: { backgroundColor: '#dcfce7' },
  liveDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#94a3b8' },
  liveDotOn: { backgroundColor: '#16a34a' },
  liveBadgeTxt: { fontSize: 10, fontWeight: '800', color: '#64748b', textTransform: 'uppercase' },
  tripMeta: { paddingHorizontal: 14, paddingBottom: 8, gap: 5 },
  tripMetaRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  tripMetaTxt: { fontSize: 13, color: '#475569', fontWeight: '500' },
  tripAction: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 4,
    paddingHorizontal: 14, paddingVertical: 10, borderTopWidth: 1, borderTopColor: '#f1f5f9',
  },
  tripActionTxt: { fontSize: 13, fontWeight: '700', color: '#3b82f6' },

  // Tracking view
  trackingTitleRow: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    paddingHorizontal: 16, paddingTop: 16, marginBottom: 12,
  },
  trackingRoute: { fontSize: 18, fontWeight: '800', color: '#0f172a', marginBottom: 2 },
  trackingSubRoute: { fontSize: 13, color: '#64748b' },

  // Map card
  mapCard: {
    marginHorizontal: 16, marginBottom: 16, backgroundColor: '#fff',
    borderRadius: 16, overflow: 'hidden', height: 240,
    borderWidth: 1, borderColor: '#e2e8f0',
    shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.06, shadowRadius: 8, elevation: 3,
  },
  mapImage: { width: '100%', height: '100%' },
  mapPlaceholder: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f1f5f9' },
  mapPlaceholderTxt: { marginTop: 12, fontSize: 15, fontWeight: '600', color: '#64748b' },
  openMapBtn: {
    position: 'absolute', bottom: 10, right: 10,
    flexDirection: 'row', alignItems: 'center', gap: 4,
    backgroundColor: 'rgba(255,255,255,0.95)', paddingHorizontal: 10, paddingVertical: 6,
    borderRadius: 8, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.1, shadowRadius: 4, elevation: 2,
  },
  openMapBtnTxt: { fontSize: 12, fontWeight: '700', color: '#3b82f6' },

  // Stats grid
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, paddingHorizontal: 16, marginBottom: 12 },
  statCard: {
    flex: 1, minWidth: (width - 52) / 2, backgroundColor: '#fff', borderRadius: 12,
    padding: 12, borderWidth: 1, borderColor: '#e2e8f0', gap: 4,
  },
  statLabel: { fontSize: 10, fontWeight: '700', color: '#94a3b8', textTransform: 'uppercase', letterSpacing: 0.4 },
  statValue: { fontSize: 14, fontWeight: '700', color: '#1e293b' },

  lastRefreshTxt: {
    textAlign: 'center', fontSize: 11, color: '#94a3b8', marginBottom: 12, paddingHorizontal: 16,
  },
  refreshBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
    backgroundColor: '#eff6ff', borderRadius: 12, paddingVertical: 12,
    marginHorizontal: 16, borderWidth: 1, borderColor: '#bfdbfe',
  },
  refreshBtnTxt: { fontSize: 14, fontWeight: '700', color: '#3b82f6' },
});
