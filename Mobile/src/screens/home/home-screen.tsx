import { Alert, ScrollView, StyleSheet, Text, View } from "react-native";

import { HeroSection } from "@/src/components/home/hero-section";
import { PopularRouteCard } from "@/src/components/home/popular-route-card";
import { QuickActionGrid } from "@/src/components/home/quick-action-grid";
import { SafetyBanner } from "@/src/components/home/safety-banner";
import { SearchTripCard } from "@/src/components/home/search-trip-card";
import { VoucherSection } from "@/src/components/home/voucher-section";
import { popularRoutes, quickActions } from "@/src/constants/home-data";


import { useRouter } from "expo-router";

export function HomeScreen() {
  const router = useRouter();
  
  const handleSearch = (params: any) => {
    router.push({
      pathname: "/search/results",
      params: {
        from: params.from,
        to: params.to,
        date: params.date,
      }
    });
  };

  return (
    <View style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        bounces={false}
      >
        <HeroSection />

        <SearchTripCard
          from="Hà Nội"
          to="Hải Phòng"
          onPressSearch={handleSearch}
        />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Vigilant AI Ecosystem</Text>
          <Text style={styles.linkText}>Xem tất cả</Text>
        </View>
        <QuickActionGrid actions={quickActions} />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Tuyến Đường Phổ Biến</Text>
        </View>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.carousel}
        >
          {popularRoutes.map((route) => (
            <PopularRouteCard key={route.id} route={route} />
          ))}
        </ScrollView>

        <VoucherSection />

        <View style={styles.sectionBottom}>
          <SafetyBanner />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  content: {
    paddingBottom: 40,
    backgroundColor: "#f8fafc",
  },
  sectionHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    marginTop: 32,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: "#0f172a",
  },
  linkText: {
    fontSize: 14,
    fontWeight: "700",
    color: "#0052cc",
  },
  carousel: {
    paddingLeft: 20,
  },
  sectionBottom: {
    marginTop: 10,
    marginBottom: 20,
  },
});
