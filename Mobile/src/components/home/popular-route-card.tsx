import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import { Image } from "expo-image";
import { StyleSheet, Text, View } from "react-native";

import { PopularRoute } from "@/src/constants/home-data";

type PopularRouteCardProps = {
  route: PopularRoute;
};

export function PopularRouteCard({ route }: PopularRouteCardProps) {
  return (
    <View style={styles.card}>
      <Image style={styles.image} source={{ uri: route.image }} />
      <View style={styles.badge}>
        <Text style={styles.badgeText}>MỖI NGÀY</Text>
      </View>
      <View style={styles.content}>
        <Text style={styles.routeLine}>
          {route.from} {"->"} {route.to}
        </Text>
        <View style={styles.durationRow}>
          <MaterialIcons name="schedule" size={14} color="#64748b" />
          <Text style={styles.duration}>{route.duration}</Text>
        </View>
        <View style={styles.bottomRow}>
          <Text style={styles.price}>{route.startPrice}</Text>
          <View style={styles.arrowButton}>
            <MaterialIcons name="arrow-forward" size={20} color="#0052cc" />
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    width: 260,
    borderRadius: 16,
    backgroundColor: "#ffffff",
    overflow: "hidden",
    marginRight: 16,
    shadowColor: "#000",
    shadowOpacity: 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
    marginBottom: 20,
  },
  image: {
    width: "100%",
    height: 120,
  },
  badge: {
    position: "absolute",
    top: 12,
    right: 12,
    backgroundColor: "rgba(255,255,255,0.9)",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  badgeText: {
    fontSize: 10,
    fontWeight: "700",
    color: "#0052cc",
  },
  content: {
    padding: 16,
    gap: 8,
  },
  routeLine: {
    fontSize: 16,
    fontWeight: "700",
    color: "#0f172a",
  },
  durationRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  duration: {
    fontSize: 13,
    color: "#64748b",
  },
  bottomRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginTop: 8,
  },
  price: {
    fontSize: 18,
    color: "#0052cc",
    fontWeight: "800",
  },
  arrowButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "#eff6ff",
    justifyContent: "center",
    alignItems: "center",
  },
});
