import React, { useState, useEffect } from 'react';
import { StyleSheet, Text, View, ScrollView, RefreshControl, ActivityIndicator, TouchableOpacity } from 'react-native';
import { Stack, useRouter } from 'expo-router';
import { VoucherCard } from '@/src/components/voucher/VoucherCard';
import clientApi from '@/src/services/client-api';
import Animated, { FadeInUp } from 'react-native-reanimated';
import { IconSymbol } from '@/src/components/ui/icon-symbol';

export default function MyVouchersScreen() {
  const [vouchers, setVouchers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const router = useRouter();

  const fetchMyVouchers = async () => {
    try {
      const res = await clientApi.getMyVouchers();
      if (res.data.success) {
        setVouchers(res.data.data || []);
      }
    } catch (error) {
      console.error('Lỗi tải voucher cá nhân:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchMyVouchers();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchMyVouchers();
  };

  return (
    <View style={styles.container}>
      <Stack.Screen 
        options={{ 
          title: 'Ví Voucher',
          headerTitleStyle: { fontWeight: '800' },
          headerShadowVisible: false,
          headerRight: () => (
            <TouchableOpacity onPress={() => router.push('/vouchers/hunt')}>
              <Text style={styles.huntLink}>Săn thêm</Text>
            </TouchableOpacity>
          )
        }} 
      />

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        <View style={styles.header}>
          <Text style={styles.title}>Voucher của tôi 🎟️</Text>
          <Text style={styles.subtitle}>Sử dụng mã voucher khi đặt vé để được giảm giá trực tiếp.</Text>
        </View>

        {loading ? (
          <View style={styles.centerContainer}>
            <ActivityIndicator size="large" color="#4f46e5" />
          </View>
        ) : vouchers.length === 0 ? (
          <View style={styles.emptyContainer}>
            <View style={styles.emptyIconContainer}>
              <IconSymbol name="gift.fill" size={60} color="#cbd5e1" />
            </View>
            <Text style={styles.emptyTitle}>Ví voucher đang trống</Text>
            <Text style={styles.emptySubtitle}>Bạn chưa có voucher nào trong ví. Hãy đi săn ngay nhé!</Text>
            <TouchableOpacity 
              style={styles.emptyAction}
              onPress={() => router.push('/vouchers/hunt')}
            >
              <Text style={styles.emptyActionText}>Săn voucher ngay</Text>
            </TouchableOpacity>
          </View>
        ) : (
          vouchers.map((v: any, index: number) => (
            <VoucherCard
              key={v.id}
              voucher={v}
              onPress={() => router.push('/(tabs)/')} // Go to home to search trips
              type="my"
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
  huntLink: {
    color: '#4f46e5',
    fontWeight: '700',
    fontSize: 14,
    marginRight: 8,
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 100,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 60,
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
    marginBottom: 24,
  },
  emptyAction: {
    backgroundColor: '#4f46e5',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
  },
  emptyActionText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 16,
  },
});
