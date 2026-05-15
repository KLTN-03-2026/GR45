import React, { useState, useEffect } from 'react';
import { StyleSheet, Text, View, ScrollView, RefreshControl, ActivityIndicator, Alert } from 'react-native';
import { Stack, useRouter } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { VoucherCard } from '@/src/components/voucher/VoucherCard';
import clientApi from '@/src/services/client-api';
import Animated, { FadeInUp } from 'react-native-reanimated';
import { IconSymbol } from '@/src/components/ui/icon-symbol';

export default function VoucherHuntingScreen() {
  const [vouchers, setVouchers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [huntingIds, setHuntingIds] = useState<Set<number>>(new Set());
  const router = useRouter();

  const fetchVouchers = async () => {
    try {
      const res = await clientApi.getHuntableVouchers();
      if (res.data.success) {
        setVouchers(res.data.data || []);
      }
    } catch (error) {
      console.error('Lỗi tải voucher:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchVouchers();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchVouchers();
  };

  const handleHunt = async (voucher: any) => {
    if (huntingIds.has(voucher.id)) return;

    setHuntingIds(prev => new Set(prev).add(voucher.id));
    try {
      const res = await clientApi.huntVoucher(voucher.id);
      if (res.data.success) {
        Alert.alert('Thành công! 🎉', `Bạn đã săn được voucher ${voucher.ma_voucher}. Voucher đã được lưu vào ví.`);
        // Refresh list
        fetchVouchers();
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Săn voucher thất bại, vui lòng thử lại sau.';
      Alert.alert('Lỗi', msg);
    } finally {
      setHuntingIds(prev => {
        const next = new Set(prev);
        next.delete(voucher.id);
        return next;
      });
    }
  };

  return (
    <View style={styles.container}>
      <Stack.Screen 
        options={{ 
          title: 'Săn Voucher',
          headerTitleStyle: { fontWeight: '800' },
          headerShadowVisible: false,
        }} 
      />

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        <Animated.View entering={FadeInUp.duration(600)} style={styles.header}>
          <Text style={styles.title}>Ưu đãi cực hot 🎁</Text>
          <Text style={styles.subtitle}>Săn ngay voucher để nhận ưu đãi đặc biệt cho chuyến đi của bạn.</Text>
        </Animated.View>

        {loading ? (
          <View style={styles.centerContainer}>
            <ActivityIndicator size="large" color="#4f46e5" />
            <Text style={styles.loadingText}>Đang tìm voucher tốt nhất...</Text>
          </View>
        ) : vouchers.length === 0 ? (
          <View style={styles.emptyContainer}>
            <View style={styles.emptyIconContainer}>
              <IconSymbol name="ticket.fill" size={60} color="#cbd5e1" />
            </View>
            <Text style={styles.emptyTitle}>Hết voucher rồi!</Text>
            <Text style={styles.emptySubtitle}>Hiện không có voucher nào khả dụng để săn. Hãy quay lại sau nhé.</Text>
          </View>
        ) : (
          vouchers.map((v: any, index: number) => (
            <VoucherCard
              key={v.id}
              voucher={v}
              isHunting={huntingIds.has(v.id)}
              onPress={() => handleHunt(v)}
              type="hunt"
            />
          ))
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  scrollContent: {
    paddingBottom: 40,
  },
  header: {
    padding: 20,
    marginBottom: 10,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
    color: '#1e293b',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 14,
    color: '#64748b',
    lineHeight: 20,
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 100,
  },
  loadingText: {
    marginTop: 12,
    color: '#64748b',
    fontSize: 14,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 80,
    paddingHorizontal: 40,
  },
  emptyIconContainer: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#334155',
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
    lineHeight: 22,
  },
});
